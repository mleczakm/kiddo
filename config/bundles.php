<?php

declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => [
        'all' => true,
    ],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => [
        'all' => true,
    ],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => [
        'all' => true,
    ],
    Symfony\Bundle\DebugBundle\DebugBundle::class => [
        'dev' => true,
    ],
    Symfony\Bundle\TwigBundle\TwigBundle::class => [
        'all' => true,
    ],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => [
        'dev' => true,
        'test' => true,
    ],
    Symfony\UX\StimulusBundle\StimulusBundle::class => [
        'all' => true,
    ],
    Symfony\UX\Turbo\TurboBundle::class => [
        'all' => true,
    ],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => [
        'all' => true,
    ],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => [
        'all' => true,
    ],
    Symfony\Bundle\MonologBundle\MonologBundle::class => [
        'all' => true,
    ],
    Symfony\Bundle\MakerBundle\MakerBundle::class => [
        'dev' => true,
    ],
    SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\SwooleBundle::class => [
        'all' => true,
    ],
    Symfonycasts\TailwindBundle\SymfonycastsTailwindBundle::class => [
        'all' => true,
    ],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => [
        'all' => true,
    ],
    Symfony\UX\LiveComponent\LiveComponentBundle::class => [
        'all' => true,
    ],
    TalesFromADev\Twig\Extra\Tailwind\Bridge\Symfony\Bundle\TalesFromADevTwigExtraTailwindBundle::class => [
        'all' => true,
    ],
    TalesFromADev\FlowbiteBundle\TalesFromADevFlowbiteBundle::class => [
        'all' => true,
    ],
    Misd\PhoneNumberBundle\MisdPhoneNumberBundle::class => [
        'all' => true,
    ],
    Zenstruck\Foundry\ZenstruckFoundryBundle::class => [
        'dev' => true,
        'test' => true,
    ],
    Dunglas\DoctrineJsonOdm\Bundle\DunglasDoctrineJsonOdmBundle::class => [
        'all' => true,
    ],
];
