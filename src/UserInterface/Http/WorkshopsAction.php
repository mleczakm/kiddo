<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Series;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WorkshopsAction extends AbstractController
{
    #[Route(path: [
        'pl' => '/warsztaty',
        'en' => 'workshops',
    ], name: 'workshops')]
    public function __invoke(): Response
    {
        $lessons = [];

        $metadata = new LessonMetadata(
            title: 'Rodzinne Muzykowanie z Pomelody',
            lead: 'Zajęcia umuzykalniające dla rodzin z dziećmi 0-6 lat',
            visualTheme: 'rgb(238, 203, 233)',
            description: 'Warsztaty prowadzone w języku polskim i angielskim, wspierające rozwój muzyczny dzieci.',
            capacity: 12,
            schedule: new \DateTimeImmutable('today, 12:30'),
            duration: 60,
            ageRange: new AgeRange(4, 6),
            category: 'Muzyka, Taniec, Śpiew',
        );

        $lessons[] = $lesson = new Lesson($metadata);
        $lesson->setSeries(new Series(new ArrayCollection([$lesson])));

        $klubMaluchaMetadata = new LessonMetadata(
            title: 'Klub Malucha',
            lead: 'Zajęcia dla najmłodszych dzieci',
            visualTheme: 'rgb(255, 223, 186)',
            description: 'Warsztaty wspierające rozwój społeczny i emocjonalny dzieci.',
            capacity: 10,
            schedule: new \DateTimeImmutable('2023-05-12 09:00'),
            duration: 150,
            ageRange: new AgeRange(1, 3),
            category: 'Rozwój społeczny'
        );
        $lessons[] = new Lesson($klubMaluchaMetadata);

        $budujemyRelacjeMetadata = new LessonMetadata(
            title: 'Budujemy Relacje',
            lead: 'Warsztat dla dzieci 3-6 lat',
            visualTheme: 'rgb(186, 255, 201)',
            description: 'Trening umiejętności społecznych, rozwój współpracy i pokonywanie nieśmiałości.',
            capacity: 12,
            schedule: new \DateTimeImmutable('2023-05-13 10:00'),
            duration: 120,
            ageRange: new AgeRange(3, 6),
            category: 'Umiejętności społeczne'
        );
        $lessons[] = new Lesson($budujemyRelacjeMetadata);

        $porankiBalagankiMetadata = new LessonMetadata(
            title: 'Poranki Bałaganki',
            lead: 'Warsztat dla najmłodszych',
            visualTheme: 'rgb(255, 186, 186)',
            description: 'Warsztaty wspierające rozwój społeczny, emocjonalny i poznawczy.',
            capacity: 8,
            schedule: new \DateTimeImmutable('2023-05-14 09:30'),
            duration: 90,
            ageRange: new AgeRange(2, 4),
            category: 'Rozwój poznawczy'
        );
        $lessons[] = new Lesson($porankiBalagankiMetadata);

        $muzykowanieMetadata = new LessonMetadata(
            title: 'Rodzinne Muzykowanie z Pomelody',
            lead: 'Zajęcia umuzykalniające dla rodzin z dziećmi 0-6 lat',
            visualTheme: 'rgb(238, 203, 233)',
            description: 'Warsztaty prowadzone w języku polskim i angielskim, wspierające rozwój muzyczny dzieci.',
            capacity: 15,
            schedule: new \DateTimeImmutable('2023-05-15 12:30'),
            duration: 60,
            ageRange: new AgeRange(0, 6),
            category: 'Muzyka, Taniec, Śpiew'
        );
        $lessons[] = new Lesson($muzykowanieMetadata);

        $cwiczymyMozgMetadata = new LessonMetadata(
            title: 'Ćwiczymy Mózg przez Ruch',
            lead: 'Warsztat bazujący na terapii zaburzeń integracji sensorycznej',
            visualTheme: 'rgb(186, 238, 255)',
            description: 'Zajęcia ruchowe wspierające rozwój układu nerwowego i sensorycznego.',
            capacity: 10,
            schedule: new \DateTimeImmutable('2023-05-16 11:00'),
            duration: 120,
            ageRange: new AgeRange(4, 7),
            category: 'Rozwój ruchowy'
        );
        $lessons[] = new Lesson($cwiczymyMozgMetadata);

        $fabrCzekoladyMetadata = new LessonMetadata(
            title: 'Fabryka Czekolady',
            lead: 'Warsztat dla dzieci 2-4 lata',
            visualTheme: 'rgb(255, 238, 186)',
            description: 'Dekorowanie czekolady, poznawanie historii i próbowanie różnych rodzajów czekolady.',
            capacity: 8,
            schedule: new \DateTimeImmutable('2023-05-17 14:00'),
            duration: 90,
            ageRange: new AgeRange(2, 4),
            category: 'Kreatywność'
        );
        $lessons[] = new Lesson($fabrCzekoladyMetadata);

        return $this->render('workshops.html.twig', [
            'workshops' => $lessons,
        ]);
    }
}
