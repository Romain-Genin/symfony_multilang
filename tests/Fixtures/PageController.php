<?php

declare(strict_types=1);

namespace StudioAstra\MultiLangBundle\Tests\Fixtures;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class PageController
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render('page.html.twig'));
    }
}
