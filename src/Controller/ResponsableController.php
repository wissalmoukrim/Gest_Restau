<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/responsable')]
#[IsGranted('ROLE_RESPONSABLE')]
class ResponsableController extends AbstractController
{
    #[Route('/', name: 'responsable_index')]
    public function index(CommandeRepository $commandeRepo): Response
    {
        // Commandes avec problèmes
        $commandesProblemes = $commandeRepo->findBy(
            ['statut' => 'probleme'],
            ['date' => 'DESC']
        );
        
        // Dernières commandes
        $dernieresCommandes = $commandeRepo->findBy(
            [],
            ['date' => 'DESC'],
            10
        );

        return $this->render('responsable/index.html.twig', [
            'commandesProblemes' => $commandesProblemes,
            'dernieresCommandes' => $dernieresCommandes,
        ]);
    }

    #[Route('/commandes', name: 'responsable_commandes')]
    public function commandes(CommandeRepository $commandeRepo): Response
    {
        $commandes = $commandeRepo->findBy([], ['date' => 'DESC']);

        return $this->render('responsable/commandes.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/commande/{id}', name: 'responsable_commande_show')]
    public function showCommande(Commande $commande): Response
    {
        return $this->render('responsable/commande_show.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/commande/{id}/resoudre', name: 'responsable_commande_resoudre', methods: ['POST'])]
    public function resoudreCommande(
        Commande $commande, 
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $solution = $request->request->get('solution', 'Problème résolu');
        
        // Marquer comme résolu
        $commande->setStatut('resolu');
        
        // Ajouter une note
        $notes = $commande->getNotes() ?? '';
        $notes .= "\n\n--- RÉSOLU PAR RESPONSABLE ---\n";
        $notes .= "Date: " . date('d/m/Y H:i') . "\n";
        
        // CORRECTION DÉFINITIVE - Vérification de type
        $user = $this->getUser();
        if ($user instanceof User) {
            // On est SÛR que c'est un User App\Entity\User
            $notes .= "Responsable: " . $user->getEmail() . "\n";
        } else {
            $notes .= "Responsable: Système\n";
        }
        
        $notes .= "Solution: " . $solution;
        $commande->setNotes($notes);
        
        $em->flush();
        
        $this->addFlash('success', 'Problème résolu avec succès !');
        return $this->redirectToRoute('responsable_commandes');
    }

    #[Route('/commande/{id}/assigner', name: 'responsable_commande_assigner', methods: ['POST'])]
    public function assignerCommande(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): Response {
        $chefId = $request->request->get('chef');
        $livreurId = $request->request->get('livreur');
        
        if ($chefId) {
            $chef = $userRepo->find($chefId);
            if ($chef) {
                $commande->setChef($chef);
                if ($commande->getStatut() === 'en_attente') {
                    $commande->setStatut('en_preparation');
                }
            }
        }
        
        if ($livreurId) {
            $livreur = $userRepo->find($livreurId);
            if ($livreur) {
                $commande->setLivreur($livreur);
                if ($commande->getStatut() === 'en_preparation') {
                    $commande->setStatut('en_livraison');
                }
            }
        }
        
        $em->flush();
        
        $this->addFlash('success', 'Commande assignée !');
        return $this->redirectToRoute('responsable_commande_show', ['id' => $commande->getId()]);
    }

    #[Route('/commande/{id}/changer-statut', name: 'responsable_commande_changer_statut', methods: ['POST'])]
    public function changerStatut(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $nouveauStatut = $request->request->get('statut');
        
        if (in_array($nouveauStatut, ['en_attente', 'en_preparation', 'en_livraison', 'terminee', 'probleme', 'annulee'])) {
            $commande->setStatut($nouveauStatut);
            $em->flush();
            
            $this->addFlash('success', 'Statut mis à jour !');
        }
        
        return $this->redirectToRoute('responsable_commande_show', ['id' => $commande->getId()]);
    }
}