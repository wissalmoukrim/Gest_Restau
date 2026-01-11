<?php

namespace App\Controller;

use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChefController extends AbstractController
{
    #[Route('/chef/dashboard', name: 'chef_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        // MODIFIEZ CETTE LIGNE :
        $commandes = $em->getRepository(Commande::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->where('u IS NOT NULL')  // ← IMPORTANT: exclut les commandes sans utilisateur
            ->andWhere('c.statut != :termine')
            ->setParameter('termine', 'terminee')
            ->orderBy('c.date', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('chef/index.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/chef/interface', name: 'chef_interface')]
    public function interface(): Response
    {
        return $this->render('chef/interface.html.twig');
    }

    #[Route('/chef/commande/{id}/update-status', name: 'chef_update_commande', methods: ['POST'])]
    public function updateStatus(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        $newStatus = $request->request->get('status');
        $commande->setStatut($newStatus); 
        $em->flush();

        return $this->redirectToRoute('chef_dashboard');
    }
}