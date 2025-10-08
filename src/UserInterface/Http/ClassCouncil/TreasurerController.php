<?php

declare(strict_types=1);

namespace App\UserInterface\Http\ClassCouncil;

use App\Entity\Setting;
use App\Entity\ClassCouncil\ClassExpense;
use App\Entity\ClassCouncil\ClassMembership;
use App\Entity\ClassCouncil\ClassRole;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\ClassCouncil\ClassExpenseRepository;
use App\Repository\ClassCouncil\ClassMembershipRepository;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Repository\ClassCouncil\StudentRepository;
use App\Repository\UserRepository;
use App\Tenant\TenantContext;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class TreasurerController extends AbstractController
{
    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly StudentRepository $students,
        private readonly ClassMembershipRepository $memberships,
        private readonly UserRepository $users,
        private readonly StudentPaymentRepository $studentPayments,
        private readonly ClassExpenseRepository $expenses,
        private readonly TenantContext $tenantContext,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'cc_dashboard', methods: [
        'GET',
    ], condition: "request.attributes.get('_tenant') and (request.attributes.get('_tenant').getName() matches '/classpay/i' or request.attributes.get('_tenant').getName() matches '/Skarbiec Mleczki/i')")]
    public function index(): Response
    {
        if (! $this->getUser()) {
            return $this->redirectToRoute('app_login');
        }


        /** @var ?Tenant $tenant */
        $tenant = $this->tenantContext->getTenant();
        $class = $tenant ? $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]) : null;

        $myStudents = [];
        $paymentsByStudent = [];
        $hasMissingPayments = false;

        if ($tenant && $class && $this->getUser()) {
            // Fetch students linked to current user in this class
            $qb = $this->students->createQueryBuilder('s');
            $qb->innerJoin('s.parents', 'p')
                ->innerJoin('s.classRoom', 'c')
                ->andWhere('p.id = :userId')
                ->andWhere('c.id = :classId')
                ->setParameter('userId', $this->getUser()->getId())
                ->setParameter('classId', $class->getId(), 'ulid');
            /** @var array<int, Student> $myStudents */
            $myStudents = $qb->getQuery()
                ->getResult();

            if ($myStudents) {
                // Load all payments for these students
                $all = $this->studentPayments->findForStudents($myStudents);
                // Index by student id
                foreach ($all as $sp) {
                    $sid = (string) $sp->getStudent()
                        ->getId();
                    $paymentsByStudent[$sid] ??= [];
                    $paymentsByStudent[$sid][] = $sp;
                    if ($sp->getStatus() !== StudentPayment::STATUS_PAID) {
                        $hasMissingPayments = true;
                    }
                }
            }
        }

        return $this->render('class_council/dashboard.html.twig', [
            'classRoom' => $class,
            'students' => $myStudents,
            'paymentsByStudent' => $paymentsByStudent,
            'hasMissingPayments' => $hasMissingPayments,
        ]);
    }

    #[Route('/classes/create', name: 'cc_create_class', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createClass(Request $request): Response
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant instanceof Tenant) {
            throw $this->createNotFoundException();
        }

        // Only allow one class for now
        $existing = $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]);
        if ($existing) {
            return $this->redirectToRoute('cc_dashboard');
        }

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            if ($name === '') {
                $this->addFlash('error', 'Nazwa klasy jest wymagana');
            } else {
                $class = new ClassRoom($tenant, $name);
                $this->em->persist($class);
                $this->em->flush();
                return $this->redirectToRoute('cc_dashboard');
            }
        }

        return $this->render('class_council/create_class.html.twig');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/students', name: 'cc_students', methods: ['GET', 'POST'])]
    public function students(Request $request): Response
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant instanceof Tenant) {
            throw $this->createNotFoundException();
        }
        $class = $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]);
        if (! $class) {
            return $this->redirectToRoute('cc_create_class');
        }

        if ($request->isMethod('POST')) {
            $first = trim((string) $request->request->get('first_name', ''));
            $last = trim((string) $request->request->get('last_name', ''));
            if ($first === '' || $last === '') {
                $this->addFlash('error', 'Imię i nazwisko są wymagane');
            } else {
                $student = new Student($class, $first, $last);

                $student->addParent($this->getUser());
                $this->em->persist($student);
                $this->em->flush();

                // Initialize required payments for this student based on tenant templates
                $this->initializeStudentPayments($student);

                $this->addFlash('success', 'Dodano ucznia');

                return $this->redirectToRoute('cc_dashboard');
            }
        }

        $list = $this->students->findBy([
            'classRoom' => $class,
        ], [
            'lastName' => 'ASC',
            'firstName' => 'ASC',
        ]);

        // If treasurer, load payments and balances per student
        $isTreasurer = $this->isCurrentUserTreasurer($class);
        $paymentsByStudent = [];
        $balances = [];
        if ($isTreasurer && $list !== []) {
            $allPayments = $this->studentPayments->findForStudents($list);
            $zero = Money::of(0, 'PLN');
            foreach ($list as $s) {
                $sid = (string) $s->getId();
                $paymentsByStudent[$sid] = [];
                $balances[$sid] = [
                    'required' => $zero,
                    'paid' => $zero,
                    'balance' => $zero,
                ];
            }
            foreach ($allPayments as $sp) {
                $sid = (string) $sp->getStudent()
                    ->getId();
                $paymentsByStudent[$sid][] = $sp;
                if (isset($balances[$sid])) {
                    $balances[$sid]['required'] = $balances[$sid]['required']->plus($sp->getAmount());
                    if ($sp->getStatus() === StudentPayment::STATUS_PAID) {
                        $balances[$sid]['paid'] = $balances[$sid]['paid']->plus($sp->getAmount());
                    }
                }
            }
            foreach ($balances as $sid => $b) {
                $balances[$sid]['balance'] = $b['required']->minus($b['paid']);
            }
        }

        // Determine which students current user can edit
        $editableIds = [];
        $user = $this->getUser();
        if ($user instanceof User) {
            foreach ($list as $s) {
                if ($isTreasurer || $s->getParents()->contains($user)) {
                    $editableIds[] = (string) $s->getId();
                }
            }
        }

        return $this->render('class_council/students.html.twig', [
            'classRoom' => $class,
            'students' => $list,
            'isTreasurer' => $isTreasurer,
            'paymentsByStudent' => $paymentsByStudent,
            'balances' => $balances,
            'editableIds' => $editableIds,
        ]);
    }

    #[Route('/students/{id}/edit', name: 'cc_student_edit', requirements: [
        'id' => '[0-9A-HJKMNPQRSTUVWXYZ]{26}',
    ], methods: ['GET', 'POST'])]
    public function editStudent(string $id, Request $request): Response
    {
        $tenant = $this->tenantContext->getTenant();
        $class = $tenant ? $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]) : null;
        if (! $class) {
            throw $this->createNotFoundException();
        }
        try {
            $ulid = Ulid::fromString($id);
        } catch (\Throwable) {
            throw $this->createNotFoundException();
        }
        /** @var ?Student $student */
        $student = $this->students->find($ulid);
        if (! $student || $student->getClassRoom()->getId() !== $class->getId()) {
            throw $this->createNotFoundException();
        }

        // Permissions: treasurer can edit any; parent can edit if linked
        $user = $this->getUser();
        $canEdit = $this->isCurrentUserTreasurer($class) || ($user instanceof User && $student->getParents()->contains(
            $user
        ));
        if (! $canEdit) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $first = trim((string) $request->request->get('first_name', ''));
            $last = trim((string) $request->request->get('last_name', ''));
            if ($first === '' || $last === '') {
                $this->addFlash('error', 'Imię i nazwisko są wymagane');
            } else {
                $student->setFirstName($first);
                $student->setLastName($last);
                $this->em->flush();
                $this->addFlash('success', 'Zapisano zmiany');
                return $this->redirectToRoute('cc_students');
            }
        }

        return $this->render('class_council/student_edit.html.twig', [
            'classRoom' => $class,
            'student' => $student,
        ]);
    }

    #[Route('/students/{id}/delete', name: 'cc_student_delete', requirements: [
        'id' => '[0-9A-HJKMNPQRSTUVWXYZ]{26}',
    ], methods: ['POST'])]
    public function deleteStudent(string $id): Response
    {
        $tenant = $this->tenantContext->getTenant();
        $class = $tenant ? $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]) : null;
        if (! $class) {
            throw $this->createNotFoundException();
        }
        // Only treasurer can delete students
        $this->assertTreasurer($class);

        try {
            $ulid = Ulid::fromString($id);
        } catch (\Throwable) {
            throw $this->createNotFoundException();
        }
        /** @var ?Student $student */
        $student = $this->students->find($ulid);
        if (! $student || $student->getClassRoom()->getId() !== $class->getId()) {
            throw $this->createNotFoundException();
        }

        // Remove dependent student payments first to satisfy FK constraints
        $payments = $this->studentPayments->findForStudents([$student]);
        foreach ($payments as $sp) {
            $this->em->remove($sp);
        }

        $this->em->remove($student);
        $this->em->flush();

        $this->addFlash('success', 'Uczeń został usunięty');
        return $this->redirectToRoute('cc_students');
    }

    /**
     * Initialize required payments for a given student based on tenant setting cc.required_payments.
     */
    private function initializeStudentPayments(Student $student): void
    {
        // Read templates for tenant
        $tenant = $student->getClassRoom()
            ->getTenant();
        $settingRepo = $this->em->getRepository(Setting::class);
        /** @var ?Setting $tpl */
        $tpl = $settingRepo->findOneBy([
            'tenant' => $tenant,
            'key' => 'cc.required_payments',
        ]);
        $items = is_array($tpl?->getContent()) ? $tpl->getContent() : [];
        if ($items === []) {
            return;
        }

        $created = false;
        foreach ($items as $item) {
            $label = is_array($item) ? ($item['label'] ?? null) : ($item->label ?? null);
            $amountStr = is_array($item) ? ($item['amount_pln'] ?? null) : ($item->amount_pln ?? null);
            if (! is_string($label) || $label === '' || ! is_string($amountStr) || $amountStr === '') {
                continue;
            }
            // Avoid duplication
            if ($this->studentPayments->existsForStudentAndLabel($student, $label)) {
                continue;
            }
            $amount = Money::of($amountStr, 'PLN');
            $sp = new StudentPayment($student, $label, $amount);
            $this->em->persist($sp);
            $created = true;
        }
        if ($created) {
            $this->em->flush();
        }
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/students/link-parent', name: 'cc_link_parent', methods: ['GET', 'POST'])]
    public function linkParent(Request $request): Response
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant instanceof Tenant) {
            throw $this->createNotFoundException();
        }
        $class = $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]);
        if (! $class) {
            return $this->redirectToRoute('cc_create_class');
        }

        if ($request->isMethod('POST')) {
            $studentId = (string) $request->request->get('student_id');
            $email = trim((string) $request->request->get('email', ''));
            $student = null;
            if ($studentId !== '') {
                try {
                    $ulid = Ulid::fromString($studentId);
                    $student = $this->students->find($ulid);
                } catch (\Throwable) {
                    $student = null;
                }
            }
            /** @var ?User $user */
            $user = $email !== '' ? $this->users->findOneBy([
                'email' => $email,
            ]) : null;

            if (! $student || ! $user) {
                $this->addFlash('error', 'Wybierz ucznia i podaj poprawny e-mail rodzica');
            } else {
                // Link parent to student
                $student->addParent($user);
                // Ensure user is linked to tenant
                $user->addTenant($tenant);
                // Ensure user has membership as parent
                $membership = $this->memberships->findOneBy([
                    'user' => $user,
                    'classRoom' => $class,
                ]);
                if (! $membership) {
                    $membership = new ClassMembership($user, $class, ClassRole::PARENT);
                    $this->em->persist($membership);
                }
                $this->em->flush();
                $this->addFlash('success', 'Rodzic został powiązany z uczniem');
                return $this->redirectToRoute('cc_students');
            }
        }

        $list = $this->students->findBy([
            'classRoom' => $class,
        ], [
            'lastName' => 'ASC',
            'firstName' => 'ASC',
        ]);
        return $this->render('class_council/link_parent.html.twig', [
            'students' => $list,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/treasurer', name: 'cc_treasurer_overview', methods: ['GET'])]
    public function treasurerOverview(): Response
    {
        $tenant = $this->tenantContext->getTenant();
        $class = $tenant ? $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]) : null;
        if (! $class) {
            return $this->redirectToRoute('cc_create_class');
        }
        $this->assertTreasurer($class);

        $students = $this->students->findBy([
            'classRoom' => $class,
        ], [
            'lastName' => 'ASC',
            'firstName' => 'ASC',
        ]);
        $studentPayments = $this->studentPayments->findForStudents($students);

        $sumPaid = Money::of(0, 'PLN');
        $sumRequired = Money::of(0, 'PLN');
        foreach ($studentPayments as $sp) {
            $sumRequired = $sumRequired->plus($sp->getAmount());
            if ($sp->getStatus() === StudentPayment::STATUS_PAID) {
                $sumPaid = $sumPaid->plus($sp->getAmount());
            }
        }
        $expenses = $class ? $this->expenses->findByClass($class) : [];
        $sumExpenses = Money::of(0, 'PLN');
        foreach ($expenses as $e) {
            $sumExpenses = $sumExpenses->plus($e->getAmount());
        }
        $balance = $sumPaid->minus($sumExpenses);

        return $this->render('class_council/treasurer_overview.html.twig', [
            'classRoom' => $class,
            'students' => $students,
            'studentPayments' => $studentPayments,
            'expenses' => $expenses,
            'sumRequired' => $sumRequired,
            'sumPaid' => $sumPaid,
            'sumExpenses' => $sumExpenses,
            'balance' => $balance,
        ]);
    }

    private function assertTreasurer(ClassRoom $class): void
    {
        $user = $this->getUser();
        if (! $user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $m = $this->memberships->findOneBy([
            'user' => $user,
            'classRoom' => $class,
        ]);
        if (! $m || $m->getRole() !== ClassRole::TREASURER) {
            throw $this->createAccessDeniedException();
        }
    }

    private function isCurrentUserTreasurer(ClassRoom $class): bool
    {
        $user = $this->getUser();
        if (! $user instanceof User) {
            return false;
        }
        $m = $this->memberships->findOneBy([
            'user' => $user,
            'classRoom' => $class,
        ]);
        return $m !== null && $m->getRole() === ClassRole::TREASURER;
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/expenses', name: 'cc_expenses', methods: ['GET', 'POST'])]
    public function expenses(Request $request): Response
    {
        $tenant = $this->tenantContext->getTenant();
        $class = $tenant ? $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]) : null;
        if (! $class) {
            return $this->redirectToRoute('cc_create_class');
        }
        $this->assertTreasurer($class);

        if ($request->isMethod('POST')) {
            $label = trim((string) $request->request->get('label', ''));
            $amountStr = trim((string) $request->request->get('amount_pln', ''));
            $spentAtStr = (string) $request->request->get('spent_at', '');
            if ($label !== '' && $amountStr !== '') {
                $amount = Money::of($amountStr, 'PLN');
                $spentAt = $spentAtStr !== '' ? new \DateTimeImmutable($spentAtStr) : null;
                $expense = new ClassExpense($class, $label, $amount, $spentAt);
                $this->em->persist($expense);
                $this->em->flush();
                $this->addFlash('success', 'Wydatek dodany');
                return $this->redirectToRoute('cc_expenses');
            }
            $this->addFlash('error', 'Podaj nazwę i kwotę wydatku');
        }

        $list = $this->expenses->findByClass($class);
        return $this->render('class_council/expenses.html.twig', [
            'classRoom' => $class,
            'expenses' => $list,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/payments/templates', name: 'cc_payment_templates', methods: ['GET', 'POST'])]
    public function paymentTemplates(Request $request): Response
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant instanceof Tenant) {
            throw $this->createNotFoundException();
        }
        $class = $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]);
        if (! $class) {
            return $this->redirectToRoute('cc_create_class');
        }
        $this->assertTreasurer($class);

        $settingRepo = $this->em->getRepository(Setting::class);
        /** @var ?Setting $setting */
        $setting = $settingRepo->findOneBy([
            'tenant' => $tenant,
            'key' => 'cc.required_payments',
        ]);
        if (! $setting) {
            $setting = new Setting();
            $setting->setKey('cc.required_payments');
            $setting->setTenant($tenant);
            $setting->setContent([]);
            $this->em->persist($setting);
            $this->em->flush();
        }

        if ($request->isMethod('POST')) {
            $label = trim((string) $request->request->get('label', ''));
            $amountStr = trim((string) $request->request->get('amount_pln', ''));
            if ($label !== '' && $amountStr !== '') {
                $content = is_array($setting->getContent()) ? $setting->getContent() : [];
                $content[] = [
                    'label' => $label,
                    'amount_pln' => $amountStr,
                ];
                $setting->setContent($content);
                $this->em->flush();
                $this->addFlash('success', 'Dodano szablon składki');
                return $this->redirectToRoute('cc_payment_templates');
            }
            $this->addFlash('error', 'Podaj nazwę i kwotę składki');
        }

        return $this->render('class_council/payment_templates.html.twig', [
            'classRoom' => $class,
            'templates' => $setting->getContent(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/payments/templates/apply', name: 'cc_payment_templates_apply', methods: ['POST'])]
    public function applyTemplates(): Response
    {
        $tenant = $this->tenantContext->getTenant();
        $class = $tenant ? $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]) : null;
        if (! $class) {
            return $this->redirectToRoute('cc_create_class');
        }
        $this->assertTreasurer($class);

        $students = $this->students->findBy([
            'classRoom' => $class,
        ]);
        foreach ($students as $s) {
            $this->initializeStudentPayments($s);
        }
        $this->addFlash('success', 'Szablony zastosowane do wszystkich uczniów');
        return $this->redirectToRoute('cc_payment_templates');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/payments/templates/{index}/delete', name: 'cc_payment_templates_delete', requirements: [
        'index' => '\\d+',
    ], methods: ['POST'])]
    public function deleteTemplate(int $index): Response
    {
        $tenant = $this->tenantContext->getTenant();
        $class = $tenant ? $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]) : null;
        if (! $class) {
            return $this->redirectToRoute('cc_create_class');
        }
        $this->assertTreasurer($class);

        $settingRepo = $this->em->getRepository(Setting::class);
        /** @var ?Setting $setting */
        $setting = $settingRepo->findOneBy([
            'tenant' => $tenant,
            'key' => 'cc.required_payments',
        ]);
        if (! $setting) {
            $this->addFlash('error', 'Nie znaleziono szablonów do usunięcia');
            return $this->redirectToRoute('cc_payment_templates');
        }

        $content = is_array($setting->getContent()) ? $setting->getContent() : [];
        if ($index < 0 || $index >= count($content)) {
            $this->addFlash('error', 'Nieprawidłowy indeks szablonu');
            return $this->redirectToRoute('cc_payment_templates');
        }

        // Remove item at index and reindex array
        array_splice($content, $index, 1);
        $setting->setContent(array_values($content));
        $this->em->flush();

        $this->addFlash('success', 'Szablon został usunięty');
        return $this->redirectToRoute('cc_payment_templates');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/student-payment/{id}/generate', name: 'cc_generate_payment', methods: ['POST'])]
    public function generatePayment(string $id): Response
    {
        $tenant = $this->tenantContext->getTenant();
        $class = $tenant ? $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]) : null;
        if (! $class) {
            throw $this->createNotFoundException();
        }
        $this->assertTreasurer($class);

        $sp = $this->studentPayments->find(Ulid::fromString($id));
        if (! $sp) {
            throw $this->createNotFoundException();
        }

        // Choose a parent: pick first linked parent or fall back to current user
        $parents = $sp->getStudent()
            ->getParents()
            ->toArray();
        /** @var User $user */
        $user = $parents[0] ?? $this->getUser();
        if (! $user instanceof User) {
            throw $this->createNotFoundException('Parent user not found');
        }

        // Create Payment and PaymentCode
        $payment = new Payment($user, $sp->getAmount());
        $this->em->persist($payment);
        $code = new PaymentCode($payment);
        $this->em->persist($code);

        // Link back to student payment
        $sp->setPayment($payment);
        $this->em->flush();

        $this->addFlash('success', 'Utworzono płatność dla składki. Kod: ' . $code->getCode());
        return $this->redirectToRoute('cc_treasurer_overview');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/student-payment/{id}/mark-paid', name: 'cc_mark_student_payment_paid', methods: ['POST'])]
    public function markStudentPaymentPaid(string $id): Response
    {
        $tenant = $this->tenantContext->getTenant();
        $class = $tenant ? $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]) : null;
        if (! $class) {
            throw $this->createNotFoundException();
        }
        $this->assertTreasurer($class);

        $sp = $this->studentPayments->find(Ulid::fromString($id));
        if ($sp) {
            $sp->markPaid();
            $this->em->flush();
            $this->addFlash('success', 'Składka oznaczona jako opłacona');
        }
        return $this->redirectToRoute('cc_treasurer_overview');
    }
}
