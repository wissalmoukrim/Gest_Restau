<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommandeController extends AbstractController
{
    #[Route('/client/commande', name: 'app_client_commande')]
    public function index(): Response
    {
        return $this->render('client/commande/index.html.twig', [
            'controller_name' => 'Client/CommandeController',
        ]);
    }
}
