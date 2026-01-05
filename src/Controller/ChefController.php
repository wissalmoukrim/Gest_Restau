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

#[Route('/chef')]
#[IsGranted('ROLE_CHEF')]
class ChefController extends AbstractController
{
    #[Route('/', name: 'chef_index')]
    public function index(CommandeRepository $commandeRepo): Response
    {
        $user = $this->getUser();
        
        // Vérification du type
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('app_logout');
        }
        
        // Commandes assignées à ce chef en préparation
        $commandesEnPreparation = $commandeRepo->findBy(
            ['chef' => $user, 'statut' => 'en_preparation'],
            ['date' => 'ASC']
        );
        
        // Commandes prêtes à livrer (par ce chef)
        $commandesPretes = $commandeRepo->findBy(
            ['chef' => $user, 'statut' => 'pret_a_livrer'],
            ['date' => 'ASC']
        );
        
        // Commandes terminées (par ce chef)
        $commandesTerminees = $commandeRepo->findBy(
            ['chef' => $user, 'statut' => 'terminee'],
            ['date' => 'DESC'],
            10
        );

        return $this->render('chef/index.html.twig', [
            'commandesEnPreparation' => $commandesEnPreparation,
            'commandesPretes' => $commandesPretes,
            'commandesTerminees' => $commandesTerminees,
        ]);
    }

    #[Route('/commande/{id}', name: 'chef_commande_show')]
    public function showCommande(Commande $commande): Response
    {
        $user = $this->getUser();
        
        // Vérification du type
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('app_logout');
        }
        
        // Vérifier que la commande est assignée à ce chef
        if ($commande->getChef() !== $user) {
            $this->addFlash('error', 'Cette commande ne vous est pas assignée.');
            return $this->redirectToRoute('chef_index');
        }

        return $this->render('chef/commande_show.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/commande/{id}/preparer', name: 'chef_commande_preparer', methods: ['POST'])]
    public function preparerCommande(
        Commande $commande,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        
        // Vérification du type
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('app_logout');
        }
        
        // Vérifier que la commande est assignée à ce chef
        if ($commande->getChef() === $user) {
            $commande->setStatut('pret_a_livrer');
            
            // Ajouter une note
            $notes = $commande->getNotes() ?? '';
            $notes .= "\n\n--- PRÊTE À LIVRER ---\n";
            $notes .= "Date: " . date('d/m/Y H:i') . "\n";
            $notes .= "Chef: " . $user->getEmail();
            $commande->setNotes($notes);
            
            $em->flush();
            
            $this->addFlash('success', 'Commande marquée comme prête à livrer !');
        } else {
            $this->addFlash('error', 'Cette commande ne vous est pas assignée.');
        }

        return $this->redirectToRoute('chef_index');
    }

    #[Route('/commande/{id}/demarrer', name: 'chef_commande_demarrer', methods: ['POST'])]
    public function demarrerPreparation(
        Commande $commande,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        
        // Vérification du type
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('app_logout');
        }
        
        // Vérifier que la commande est assignée à ce chef
        if ($commande->getChef() === $user && $commande->getStatut() === 'en_attente') {
            $commande->setStatut('en_preparation');
            
            // Ajouter une note
            $notes = $commande->getNotes() ?? '';
            $notes .= "\n\n--- PRÉPARATION DÉMARRÉE ---\n";
            $notes .= "Date: " . date('d/m/Y H:i') . "\n";
            $notes .= "Chef: " . $user->getEmail();
            $commande->setNotes($notes);
            
            $em->flush();
            
            $this->addFlash('success', 'Préparation démarrée !');
        }

        return $this->redirectToRoute('chef_index');
    }

    #[Route('/commande/{id}/probleme', name: 'chef_commande_probleme', methods: ['POST'])]
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
        
        if ($commande->getChef() === $user) {
            $commande->setStatut('probleme');
            
            $notes = $commande->getNotes() ?? '';
            $notes .= "\n\n--- PROBLÈME SIGNALÉ PAR CHEF ---\n";
            $notes .= "Date: " . date('d/m/Y H:i') . "\n";
            $notes .= "Chef: " . $user->getEmail() . "\n";
            $notes .= "Raison: " . $raison;
            $commande->setNotes($notes);
            
            $em->flush();
            
            $this->addFlash('warning', 'Problème signalé au responsable !');
        }

        return $this->redirectToRoute('chef_index');
    }

    #[Route('/historique', name: 'chef_historique')]
    public function historique(CommandeRepository $commandeRepo): Response
    {
        $user = $this->getUser();
        
        // Vérification du type
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('app_logout');
        }
        
        // Toutes les commandes préparées par ce chef
        $commandes = $commandeRepo->createQueryBuilder('c')
            ->where('c.chef = :chef')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('chef', $user)
            ->setParameter('statuts', ['terminee', 'annulee', 'resolu'])
            ->orderBy('c.date', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('chef/historique.html.twig', [
            'commandes' => $commandes,
        ]);
    }
}