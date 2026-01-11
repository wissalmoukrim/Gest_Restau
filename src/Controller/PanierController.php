<?php

namespace App\Controller;

use App\Entity\Panier;
use App\Entity\Plat;
use App\Entity\PanierItem;
use App\Entity\Commande;
use App\Entity\CommandeItem;
use App\Entity\User;
use App\Repository\PanierRepository;
use App\Repository\UserRepository;
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
    public function commander(EntityManagerInterface $em, PanierRepository $panierRepository, UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Erreur d\'authentification');
            return $this->redirectToRoute('client_panier');
        }

        $panier = $panierRepository->findOneBy(['user' => $user]);
        if (!$panier || $panier->getPanierItems()->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide !');
            return $this->redirectToRoute('client_panier');
        }

        // Créer la commande
        $commande = new Commande();
        $commande->setUser($user);
        $commande->setDate(new \DateTime());
        $commande->setStatut('en_attente');

        // Assigner un chef automatiquement (premier chef trouvé)
        $chef = $userRepository->findOneByRole('ROLE_CHEF');
        $commande->setChef($chef);

        $adresse = $user->getAdresse() ?: $user->getEmail() . ' - Adresse à confirmer';
        $commande->setAdresseLivraison($adresse);

        $total = 0;

        foreach ($panier->getPanierItems() as $panierItem) {
            $commandeItem = new CommandeItem();
            $commandeItem->setCommande($commande);
            $commandeItem->setPlat($panierItem->getPlat());
            $commandeItem->setQuantite($panierItem->getQuantity());
            $commandeItem->setPrixUnitaire((string)$panierItem->getPlat()->getPrix());
            $em->persist($commandeItem);

            $total += $panierItem->getPlat()->getPrix() * $panierItem->getQuantity();
        }

        $commande->setTotal((string)$total);
        $em->persist($commande);

        // Vider le panier
        foreach ($panier->getPanierItems() as $item) {
            $em->remove($item);
        }

        $em->flush();

        $this->addFlash('success', 'Commande #' . $commande->getId() . ' passée avec succès !');
        return $this->redirectToRoute('client_commande_confirmation', ['id' => $commande->getId()]);
    }

    #[Route('/commande/{id}/show', name: 'client_commande_show')]
    public function showCommande(Commande $commande): Response
    {
        $user = $this->getUser();
        if ($commande->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès non autorisé');
        }

        return $this->render('client/commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/commande/{id}/confirmation', name: 'client_commande_confirmation')]
public function confirmation(Commande $commande): Response
{
    $user = $this->getUser();
    
    // Vérifie que l'utilisateur est bien le propriétaire de la commande
    if ($commande->getUser() !== $user) {
        throw $this->createAccessDeniedException('Accès non autorisé');
    }

    return $this->render('client/commande/confirmation.html.twig', [
        'commande' => $commande,
    ]);
}

#[Route('/ma-derniere-facture', name: 'client_derniere_facture')]
public function derniereFacture(EntityManagerInterface $em): Response
{
    $user = $this->getUser();
    
    // Trouver la dernière commande de l'utilisateur
    $commande = $em->getRepository(Commande::class)->findOneBy(
        ['user' => $user],
        ['date' => 'DESC']
    );
    
    if (!$commande) {
        $this->addFlash('warning', 'Vous n\'avez pas encore de commande');
        return $this->redirectToRoute('client_commandes');
    }
    
    return $this->redirectToRoute('client_commande_confirmation', [
        'id' => $commande->getId()
    ]);
}


#[Route('/mes-commandes', name: 'client_commandes')]
public function mesCommandes(EntityManagerInterface $em): Response
{
    $user = $this->getUser();
    
    // Solution la plus fiable et performante
    $commandes = $em->getRepository(Commande::class)->findBy(
        ['user' => $user],
        ['date' => 'DESC']
    );
    
    return $this->render('client/commande/liste.html.twig', [
        'commandes' => $commandes,
    ]);
}

    #[Route('/commande/{id}/annuler', name: 'client_commande_annuler', methods: ['POST'])]
    public function annulerCommande(Commande $commande, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if ($commande->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès non autorisé');
        }

        if ($commande->getStatut() !== 'en_attente') {
            $this->addFlash('error', 'Impossible d\'annuler une commande déjà en préparation');
            return $this->redirectToRoute('client_commande_show', ['id' => $commande->getId()]);
        }

        $commande->setStatut('annulee');
        $em->flush();

        $this->addFlash('success', 'Commande #' . $commande->getId() . ' annulée avec succès');
        return $this->redirectToRoute('client_commandes');
    }
#[Route('/commande/{id}/supprimer', name: 'client_commande_supprimer', methods: ['POST'])]
public function supprimerCommande(Commande $commande, EntityManagerInterface $em): Response
{
    $user = $this->getUser();
    
    if ($commande->getUser() !== $user) {
        throw $this->createAccessDeniedException('Accès non autorisé');
    }
    
    if ($commande->getStatut() !== 'annulee') {
        $this->addFlash('error', 'Seules les commandes annulées peuvent être supprimées');
        return $this->redirectToRoute('client_commande_show', ['id' => $commande->getId()]);
    }
    
    // Supprimer d'abord tous les items
    foreach ($commande->getCommandeItems() as $item) {
        $em->remove($item);
    }
    
    // Puis supprimer la commande
    $em->remove($commande);
    $em->flush();
    
    $this->addFlash('success', 'Commande #' . $commande->getId() . ' supprimée définitivement');
    return $this->redirectToRoute('client_commandes');
}
}
