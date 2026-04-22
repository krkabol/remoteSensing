<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/internal', name: 'app_internal_')]
#[IsGranted('ROLE_USER')]
class InternalController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('internal/index.html.twig', [
            'controller_name' => 'InternalController',
        ]);
    }

    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('internal/dashboard.html.twig', [
            'controller_name' => 'InternalController',
        ]);
    }
}