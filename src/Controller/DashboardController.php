<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(): Response
    {
        // Plats TEMPORAIRES (fake)
        $plats = [
            [
                'id' => 1,
                'nom' => 'Pizza Margherita',
                'prix' => 45,
                'image' => 'https://via.placeholder.com/300x200'
            ],
            [
                'id' => 2,
                'nom' => 'Burger Cheese',
                'prix' => 35,
                'image' => 'https://via.placeholder.com/300x200'
            ],
            [
                'id' => 3,
                'nom' => 'Tacos Poulet',
                'prix' => 30,
                'image' => 'https://via.placeholder.com/300x200'
            ],
        ];

        return $this->render('dashboard/index.html.twig', [
            'plats' => $plats
        ]);
    }
}
