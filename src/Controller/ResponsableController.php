<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/responsable')]
#[IsGranted('ROLE_RESPONSABLE')]
class ResponsableController extends AbstractController
{
#[Route('/', name: 'responsable_index')]
public function index(CommandeRepository $commandeRepo): Response
{
    // Récupérer toutes les commandes (pas seulement celles avec statut 'probleme')
    // pour voir l'historique des résolutions
    $toutesCommandes = $commandeRepo->findBy([], ['date' => 'DESC']);
    
    // Séparer les commandes en problème et les résolues
    $commandesProblemes = [];
    $commandesResolues = [];
    
    foreach ($toutesCommandes as $commande) {
        if ($commande->getStatut() === 'probleme') {
            $commandesProblemes[] = $commande;
        } elseif ($commande->getStatut() === 'resolu') {
            $commandesResolues[] = $commande;
        }
    }
    
    return $this->render('responsable/index.html.twig', [
        'commandesProblemes' => $commandesProblemes,
        'commandesResolues' => $commandesResolues,
        'toutesCommandes' => $toutesCommandes,
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

        // Ajouter une note avec responsable
        $notes = $commande->getNotes() ?? '';
        $notes .= "\n\n--- RÉSOLU PAR RESPONSABLE ---\n";
        $notes .= "Date: " . date('d/m/Y H:i') . "\n";

        $user = $this->getUser();
        if ($user instanceof User) {
            $notes .= "Responsable: " . $user->getEmail() . "\n";
        } else {
            $notes .= "Responsable: Système\n";
        }

        $notes .= "Solution: " . $solution;
        $commande->setNotes($notes);

        $em->flush();

        $this->addFlash('success', 'Problème résolu avec succès !');
        return $this->redirectToRoute('responsable_index');
    }

    #[Route('/interfacer', name: 'responsable_interfacer')]
    public function interfacer(): Response
    {
        return $this->render('responsable/interfacer.html.twig');
    }
}
