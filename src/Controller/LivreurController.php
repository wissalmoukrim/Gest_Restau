<?php

namespace App\Controller;

use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/livreur')]
#[IsGranted('ROLE_LIVREUR')]
class LivreurController extends AbstractController
{
    #[Route('', name: 'livreur_dashboard')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // Trouver les commandes assignées ou prêtes à livrer
        $commandes = $em->getRepository(Commande::class)
            ->createQueryBuilder('c')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('statuts', ['prete', 'en_livraison'])
            ->orderBy('c.date', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('livreur/index.html.twig', [
            'commandes' => $commandes
        ]);
    }

    #[Route('/commande/{id}/livrer', name: 'livreur_commande_livrer', methods: ['POST'])]
    public function livrer(Commande $commande, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // Assigner le livreur si ce n'est pas encore fait
        if (!$commande->getLivreur()) {
            $commande->setLivreur($user);
        }

        // Vérifier que c'est bien le livreur assigné
        if ($commande->getLivreur() !== $user) {
            $this->addFlash('error', 'Vous n’êtes pas assigné à cette commande.');
            return $this->redirectToRoute('livreur_dashboard');
        }

        // Changer le statut
        if ($commande->getStatut() === 'prete') {
            $commande->setStatut('en_livraison');
        } elseif ($commande->getStatut() === 'en_livraison') {
            $commande->setStatut('terminee');
        }

        $em->flush();

        $this->addFlash('success', 'Statut de la commande mis à jour !');
        return $this->redirectToRoute('livreur_dashboard');
    }

    #[Route('/interface', name: 'livreur_interface')]
public function interface(EntityManagerInterface $em): Response
{
    // Commandes prêtes ou en livraison
    $commandes = $em->getRepository(Commande::class)
        ->createQueryBuilder('c')
        ->where('c.statut IN (:statuts)')
        ->setParameter('statuts', ['prete', 'en_livraison'])
        ->orderBy('c.date', 'ASC')
        ->getQuery()
        ->getResult();

    return $this->render('livreur/interfaceliv.html.twig', [
        'commandes' => $commandes
    ]);
}


}