<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/livreur')]
#[IsGranted('ROLE_LIVREUR')]
class LivreurController extends AbstractController
{
    #[Route('/', name: 'app_livreur')]
    public function index(CommandeRepository $commandeRepo): Response
    {
        $user = $this->getUser();
        
        // Vérification du type
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('app_logout');
        }
        
        // Commandes assignées à ce livreur en cours de livraison
        $commandesEnLivraison = $commandeRepo->findBy(
            ['livreur' => $user, 'statut' => 'en_livraison'],
            ['date' => 'ASC']
        );

        // Commandes prêtes à livrer (non assignées ou assignées à ce livreur)
        $commandesPretes = $commandeRepo->createQueryBuilder('c')
            ->where('c.statut = :statut')
            ->andWhere('c.livreur IS NULL OR c.livreur = :livreur')
            ->setParameter('statut', 'pret_a_livrer')
            ->setParameter('livreur', $user)
            ->orderBy('c.date', 'ASC')
            ->getQuery()
            ->getResult();

        // Commandes terminées (par ce livreur)
        $commandesTerminees = $commandeRepo->findBy(
            ['livreur' => $user, 'statut' => 'terminee'],
            ['date' => 'DESC'],
            10
        );

        return $this->render('livreur/index.html.twig', [
            'commandesEnLivraison' => $commandesEnLivraison,
            'commandesPretes' => $commandesPretes,
            'commandesTerminees' => $commandesTerminees,
        ]);
    }

    #[Route('/commande/{id}', name: 'livreur_commande_show')]
    public function showCommande(Commande $commande): Response
    {
        $user = $this->getUser();
        
        // Vérification du type
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('app_logout');
        }
        
        // Vérifier que la commande est prête à livrer ou assignée à ce livreur
        if ($commande->getLivreur() !== $user && $commande->getLivreur() !== null) {
            $this->addFlash('error', 'Cette commande est déjà assignée à un autre livreur.');
            return $this->redirectToRoute('app_livreur');
        }

        return $this->render('livreur/commande_show.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/commande/{id}/prendre', name: 'livreur_commande_prendre', methods: ['POST'])]
    public function prendreCommande(
        Commande $commande,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        
        // Vérification du type
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('app_logout');
        }
        
        // Vérifier que la commande est prête à livrer et non assignée
        if ($commande->getStatut() === 'pret_a_livrer' && $commande->getLivreur() === null) {
            $commande->setLivreur($user);
            $commande->setStatut('en_livraison');
            
            // Ajouter une note
            $notes = $commande->getNotes() ?? '';
            $notes .= "\n\n--- PRISE EN CHARGE PAR LIVREUR ---\n";
            $notes .= "Date: " . date('d/m/Y H:i') . "\n";
            $notes .= "Livreur: " . $user->getEmail();
            $commande->setNotes($notes);
            
            $em->flush();
            
            $this->addFlash('success', 'Commande prise en charge !');
        } else {
            $this->addFlash('error', 'Cette commande ne peut pas être prise en charge.');
        }

        return $this->redirectToRoute('app_livreur');
    }

    #[Route('/commande/{id}/livrer', name: 'livreur_commande_livrer', methods: ['POST'])]
    public function livrerCommande(
        Commande $commande,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        
        // Vérification du type
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('app_logout');
        }
        
        // Vérifier que la commande est assignée à ce livreur
        if ($commande->getLivreur() === $user && $commande->getStatut() === 'en_livraison') {
            $commande->setStatut('terminee');
            
            // Ajouter une note
            $notes = $commande->getNotes() ?? '';
            $notes .= "\n\n--- LIVRÉE ---\n";
            $notes .= "Date: " . date('d/m/Y H:i') . "\n";
            $notes .= "Livreur: " . $user->getEmail();
            $commande->setNotes($notes);
            
            $em->flush();
            
            $this->addFlash('success', 'Commande marquée comme livrée !');
        } else {
            $this->addFlash('error', 'Cette commande ne peut pas être marquée comme livrée.');
        }

        return $this->redirectToRoute('app_livreur');
    }

    #[Route('/commande/{id}/probleme', name: 'livreur_commande_probleme', methods: ['POST'])]
    public function signalerProbleme(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        
        // Vérification du type
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('app_logout');
        }
        
        $raison = $request->request->get('raison');
        
        if ($commande->getLivreur() === $user) {
            $commande->setStatut('probleme');
            
            $notes = $commande->getNotes() ?? '';
            $notes .= "\n\n--- PROBLÈME SIGNALÉ PAR LIVREUR ---\n";
            $notes .= "Date: " . date('d/m/Y H:i') . "\n";
            $notes .= "Livreur: " . $user->getEmail() . "\n";
            $notes .= "Raison: " . $raison;
            $commande->setNotes($notes);
            
            $em->flush();
            
            $this->addFlash('warning', 'Problème signalé au responsable !');
        }

        return $this->redirectToRoute('app_livreur');
    }

    #[Route('/historique', name: 'livreur_historique')]
    public function historique(CommandeRepository $commandeRepo): Response
    {
        $user = $this->getUser();
        
        // Vérification du type
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('app_logout');
        }
        
        // Toutes les commandes livrées par ce livreur
        $commandes = $commandeRepo->createQueryBuilder('c')
            ->where('c.livreur = :livreur')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('livreur', $user)
            ->setParameter('statuts', ['terminee', 'annulee', 'resolu'])
            ->orderBy('c.date', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('livreur/historique.html.twig', [
            'commandes' => $commandes,
        ]);
    }
}