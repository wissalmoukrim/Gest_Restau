<?php

namespace App\Controller;

use App\Entity\Panier;
use App\Entity\Plat;
use App\Entity\PanierItem;
use App\Entity\Commande;
use App\Entity\CommandeItem;
use App\Entity\User;
use App\Repository\PanierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client/panier')]
#[IsGranted('ROLE_USER')]
class PanierController extends AbstractController
{
    #[Route('', name: 'client_panier', methods: ['GET'])]
    public function index(EntityManagerInterface $em, PanierRepository $panierRepository): Response
    {
        $user = $this->getUser();
        
        $panier = $panierRepository->findOneBy(['user' => $user]);
        
        if (!$panier) {
            $panier = new Panier();
            $panier->setUser($user);
            $em->persist($panier);
            $em->flush();
        }
        
        return $this->render('client/panier/index.html.twig', [
            'panier' => $panier,
        ]);
    }

    #[Route('/add/{id}', name: 'client_panier_add', methods: ['POST'])]
    public function add(Plat $plat, EntityManagerInterface $em, PanierRepository $panierRepository): JsonResponse
    {
        $user = $this->getUser();
        
        $panier = $panierRepository->findOneBy(['user' => $user]);
        if (!$panier) {
            $panier = new Panier();
            $panier->setUser($user);
            $em->persist($panier);
        }
        
        $itemExistant = null;
        foreach ($panier->getPanierItems() as $item) {
            if ($item->getPlat()->getId() === $plat->getId()) {
                $itemExistant = $item;
                break;
            }
        }
        
        if ($itemExistant) {
            $itemExistant->setQuantity($itemExistant->getQuantity() + 1);
        } else {
            $panierItem = new PanierItem();
            $panierItem->setPanier($panier);
            $panierItem->setPlat($plat);
            $panierItem->setQuantity(1);
            $em->persist($panierItem);
        }
        
        $em->flush();
        
        return new JsonResponse([
            'success' => true,
            'message' => 'Plat ajouté au panier!'
        ]);
    }

    #[Route('/update/{id}/{quantity}', name: 'client_panier_update', methods: ['POST'])]
    public function update(PanierItem $item, int $quantity, EntityManagerInterface $em): Response
    {
        if ($quantity <= 0) {
            $em->remove($item);
        } else {
            $item->setQuantity($quantity);
        }
        
        $em->flush();
        
        $this->addFlash('success', 'Quantité modifiée!');
        return $this->redirectToRoute('client_panier');
    }

    #[Route('/remove/{id}', name: 'client_panier_remove', methods: ['POST'])]
    public function remove(PanierItem $item, EntityManagerInterface $em): Response
    {
        $em->remove($item);
        $em->flush();
        
        $this->addFlash('success', 'Plat retiré du panier!');
        return $this->redirectToRoute('client_panier');
    }

    #[Route('/vider', name: 'client_panier_vider', methods: ['POST'])]
    public function vider(EntityManagerInterface $em, PanierRepository $panierRepository): Response
    {
        $user = $this->getUser();
        $panier = $panierRepository->findOneBy(['user' => $user]);
        
        if ($panier) {
            foreach ($panier->getPanierItems() as $item) {
                $em->remove($item);
            }
            $em->flush();
        }
        
        $this->addFlash('success', 'Panier vidé!');
        return $this->redirectToRoute('client_panier');
    }

    #[Route('/commander', name: 'client_panier_commander', methods: ['POST'])]
    public function commander(EntityManagerInterface $em, PanierRepository $panierRepository): Response
    {
        $user = $this->getUser();
        
        // Vérifier que $user est bien un User
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('client_panier');
        }
        
        $panier = $panierRepository->findOneBy(['user' => $user]);
        
        if (!$panier || $panier->getPanierItems()->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide !');
            return $this->redirectToRoute('client_panier');
        }
        
        // 1. Créer la commande
        $commande = new Commande();
        $commande->setUser($user);
        $commande->setDate(new \DateTime());
        $commande->setStatut('en_attente');
        
        // CORRECTION ICI : Gestion simple de l'adresse
        $adresse = $user->getAdresse();
        if (empty(trim($adresse ?? ''))) {
            // Utiliser l'email comme fallback
            $adresse = $user->getEmail() . ' - Adresse à confirmer';
        }
        $commande->setAdresseLivraison($adresse);
        
        $total = 0;
        
        // 2. Créer les items de commande
        foreach ($panier->getPanierItems() as $panierItem) {
            $commandeItem = new CommandeItem();
            $commandeItem->setCommande($commande);
            $commandeItem->setPlat($panierItem->getPlat());
            $commandeItem->setQuantite($panierItem->getQuantity());
            $commandeItem->setPrixUnitaire((string) $panierItem->getPlat()->getPrix());
            
            $total += $panierItem->getPlat()->getPrix() * $panierItem->getQuantity();
            
            $em->persist($commandeItem);
        }
        
        $commande->setTotal((string) $total);
        $em->persist($commande);
        
        // 3. Vider le panier
        foreach ($panier->getPanierItems() as $item) {
            $em->remove($item);
        }
        
        $em->flush();
        
        $this->addFlash('success', 'Commande #' . $commande->getId() . ' passée avec succès !');
        return $this->redirectToRoute('client_commande_confirmation', ['id' => $commande->getId()]);
    }

    // ROUTE DE CONFIRMATION
    #[Route('/commande/confirmation/{id}', name: 'client_commande_confirmation')]
    public function confirmation(Commande $commande): Response
    {
        // Vérifier que la commande appartient à l'utilisateur
        $user = $this->getUser();
        if ($commande->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès non autorisé');
        }
        
        return $this->render('client/commande/confirmation.html.twig', [
            'commande' => $commande,
        ]);
    }
    
    // ⭐⭐⭐ NOUVELLE MÉTHODE : Liste des commandes ⭐⭐⭐
    #[Route('/mes-commandes', name: 'client_commandes')]
    public function mesCommandes(): Response
    {
        $user = $this->getUser();
        
        // Vérifier que $user est bien un User
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('dashboard');
        }
        
        $commandes = $user->getCommandes();
        
        // Trier par date décroissante
        $commandesArray = $commandes->toArray();
        usort($commandesArray, function($a, $b) {
            return $b->getDate() <=> $a->getDate();
        });
        
        return $this->render('client/commande/liste.html.twig', [
            'commandes' => $commandesArray,
        ]);
    }
    
    // ⭐⭐⭐ NOUVELLE MÉTHODE : Détail d'une commande ⭐⭐⭐
    #[Route('/commande/{id}/details', name: 'client_commande_show')]
    public function showCommande(Commande $commande): Response
    {
        // Vérifier que la commande appartient à l'utilisateur
        $user = $this->getUser();
        if ($commande->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès non autorisé');
        }
        
        return $this->render('client/commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }
    
    // ⭐⭐⭐ NOUVELLE MÉTHODE : Annuler une commande ⭐⭐⭐
    #[Route('/commande/{id}/annuler', name: 'client_commande_annuler', methods: ['POST'])]
    public function annulerCommande(Commande $commande, EntityManagerInterface $em): Response
    {
        // Vérifier que la commande appartient à l'utilisateur
        $user = $this->getUser();
        if ($commande->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès non autorisé');
        }
        
        // Vérifier qu'on peut annuler (seulement si en attente)
        if ($commande->getStatut() !== 'en_attente') {
            $this->addFlash('error', 'Impossible d\'annuler une commande déjà en préparation');
            return $this->redirectToRoute('client_commande_show', ['id' => $commande->getId()]);
        }
        
        $commande->setStatut('annulee');
        $em->flush();
        
        $this->addFlash('success', 'Commande #' . $commande->getId() . ' annulée avec succès');
        return $this->redirectToRoute('client_commandes');
    }
    
    // Méthode test (optionnelle)
    #[Route('/commander/test', name: 'client_panier_commander_test', methods: ['GET'])]
    public function commanderTest(PanierRepository $panierRepository): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('dashboard');
        }
        
        $panier = $panierRepository->findOneBy(['user' => $user]);
        
        if (!$panier || $panier->getPanierItems()->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide !');
            return $this->redirectToRoute('client_panier');
        }
        
        $total = 0;
        foreach ($panier->getPanierItems() as $item) {
            $total += $item->getPlat()->getPrix() * $item->getQuantity();
        }
        
        $this->addFlash('success', '✅ Commande test réussie ! Total: ' . $total . ' DH');
        return $this->redirectToRoute('client_panier');
    }
}