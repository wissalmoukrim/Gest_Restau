<?php

namespace App\Controller;

use App\Entity\Plat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        // Récupérer TOUS les plats
        $plats = $em->getRepository(Plat::class)->findAll();

        return $this->render('dashboard/index.html.twig', [
            'plats' => $plats,
        ]);
    }

    // ⭐ AJOUTEZ CETTE MÉTHODE POUR LES DÉTAILS ⭐
    #[Route('/plat/{id}', name: 'plat_details')]
    public function details(Plat $plat): Response
    {
        return $this->render('dashboard/details.html.twig', [
            'plat' => $plat,
        ]);
    }
}