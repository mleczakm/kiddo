<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\Turbo\TurboBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\SwooleBundle;
use Symfonycasts\TailwindBundle\SymfonycastsTailwindBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use TalesFromADev\Twig\Extra\Tailwind\Bridge\Symfony\Bundle\TalesFromADevTwigExtraTailwindBundle;
use TalesFromADev\FlowbiteBundle\TalesFromADevFlowbiteBundle;
use Misd\PhoneNumberBundle\MisdPhoneNumberBundle;
use Zenstruck\Foundry\ZenstruckFoundryBundle;
use Dunglas\DoctrineJsonOdm\Bundle\DunglasDoctrineJsonOdmBundle;
use DAMA\DoctrineTestBundle\DAMADoctrineTestBundle;
use SwooleBundle\ResetterBundle\SwooleBundleResetterBundle;
use SymfonyHealthCheckBundle\SymfonyHealthCheckBundle;
use Sentry\SentryBundle\SentryBundle;
use Zenstruck\Mailer\Test\ZenstruckMailerTestBundle;
use Novaway\Bundle\FeatureFlagBundle\NovawayFeatureFlagBundle;
use Zenstruck\Messenger\Test\ZenstruckMessengerTestBundle;

return [
    FrameworkBundle::class => [
        'all' => true,
    ],
    DoctrineBundle::class => [
        'all' => true,
    ],
    DoctrineMigrationsBundle::class => [
        'all' => true,
    ],
    DebugBundle::class => [
        'dev' => true,
    ],
    TwigBundle::class => [
        'all' => true,
    ],
    WebProfilerBundle::class => [
        'dev' => true,
        'test' => true,
    ],
    StimulusBundle::class => [
        'all' => true,
    ],
    TurboBundle::class => [
        'all' => true,
    ],
    TwigExtraBundle::class => [
        'all' => true,
    ],
    SecurityBundle::class => [
        'all' => true,
    ],
    MonologBundle::class => [
        'all' => true,
    ],
    MakerBundle::class => [
        'dev' => true,
    ],
    SwooleBundle::class => [
        'all' => true,
    ],
    SymfonycastsTailwindBundle::class => [
        'all' => true,
    ],
    TwigComponentBundle::class => [
        'all' => true,
    ],
    LiveComponentBundle::class => [
        'all' => true,
    ],
    TalesFromADevTwigExtraTailwindBundle::class => [
        'all' => true,
    ],
    TalesFromADevFlowbiteBundle::class => [
        'all' => true,
    ],
    MisdPhoneNumberBundle::class => [
        'all' => true,
    ],
    ZenstruckFoundryBundle::class => [
        'dev' => true,
        'test' => true,
    ],
    DunglasDoctrineJsonOdmBundle::class => [
        'all' => true,
    ],
    DAMADoctrineTestBundle::class => [
        'test' => true,
    ],
    SwooleBundleResetterBundle::class => [
        'dev' => true,
        'prod' => true,
    ],
    SymfonyHealthCheckBundle::class => [
        'all' => true,
    ],
    SentryBundle::class => [
        'prod' => true,
    ],
    ZenstruckMailerTestBundle::class => [
        'dev' => true,
        'test' => true,
    ],
    NovawayFeatureFlagBundle::class => [
        'all' => true,
    ],
    ZenstruckMessengerTestBundle::class => [
        'test' => true,
    ],
];
