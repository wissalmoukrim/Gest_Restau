<?php

namespace App\Controller;

use App\Entity\Plat;
use App\Entity\Panier;
use App\Entity\PanierItem;
use App\Entity\Commande;
use App\Entity\User; // AJOUT IMPORT
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client')]
#[IsGranted('ROLE_USER')]
class ClientController extends AbstractController
{
    #[Route('', name: 'client_dashboard')]
    public function index(): Response
    {
        return $this->render('client/index.html.twig');
    }

    #[Route('/plat', name: 'client_plat')]
    public function plat(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $plats = $em->getRepository(Plat::class)->findAll();

        $panier = $em->getRepository(Panier::class)->findOneBy(['user' => $user]);
        if (!$panier) {
            $panier = new Panier();
            $panier->setUser($user);
            $em->persist($panier);
            $em->flush();
        }

        return $this->render('client/plat.html.twig', [
            'plats' => $plats,
            'panier' => $panier
        ]);
    }

    #[Route('/commandes', name: 'client_commandes')]
    public function commandes(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        $commandes = $em->getRepository(Commande::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.commandeItems', 'ci')
            ->leftJoin('ci.plat', 'p')
            ->addSelect('ci', 'p')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.date', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('client/commande/liste.html.twig', [
            'commandes' => $commandes
        ]);
    }

    private function calculerPanier(Panier $panier): array
    {
        $total = 0;
        $items = [];
        foreach ($panier->getPanierItems() as $i) {
            $sub = $i->getQuantity() * $i->getPlat()->getPrix();
            $items[] = [
                'id' => $i->getId(),
                'nom' => $i->getPlat()->getNom(),
                'prix' => $i->getPlat()->getPrix(),
                'image' => $i->getPlat()->getImage(),
                'quantity' => $i->getQuantity(),
                'sub' => $sub
            ];
            $total += $sub;
        }
        return ['items' => $items, 'total' => $total, 'totalItems' => count($items)];
    }

    #[Route('/panier/add/{id}', name: 'client_panier_add', methods: ['POST'])]
    public function addToPanier(Plat $plat, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $panier = $em->getRepository(Panier::class)->findOneBy(['user' => $user]);

        if (!$panier) {
            $panier = new Panier();
            $panier->setUser($user);
            $em->persist($panier);
        }

        $panierItem = $em->getRepository(PanierItem::class)->findOneBy(['panier' => $panier, 'plat' => $plat]);

        if (!$panierItem) {
            $panierItem = new PanierItem();
            $panierItem->setPanier($panier);
            $panierItem->setPlat($plat);
            $panierItem->setQuantity(1);
            
            $em->persist($panierItem);
        } else {
            $panierItem->setQuantity($panierItem->getQuantity() + 1);
        }

        $em->flush();

        return $this->json($this->calculerPanier($panier));
    }

    #[Route('/panier/update/{id}/{quantity}', name: 'client_panier_update', methods: ['POST'])]
    public function updatePanier(PanierItem $item, int $quantity, EntityManagerInterface $em): JsonResponse
    {
        if ($quantity <= 0) {
            $em->remove($item);
        } else {
            $item->setQuantity($quantity);
        }
        $em->flush();

        return $this->json($this->calculerPanier($item->getPanier()));
    }

    #[Route('/panier/remove/{id}', name: 'client_panier_remove', methods: ['POST'])]
    public function removeFromPanier(PanierItem $item, EntityManagerInterface $em): JsonResponse
    {
        $panier = $item->getPanier();
        $em->remove($item);
        $em->flush();

        return $this->json($this->calculerPanier($panier));
    }

    #[Route('/panier/vider', name: 'client_panier_vider', methods: ['POST'])]
    public function viderPanier(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $panier = $em->getRepository(Panier::class)->findOneBy(['user' => $user]);

        if ($panier) {
            foreach ($panier->getPanierItems() as $item) {
                $em->remove($item);
            }
            $em->flush();
        }

        return $this->json(['items' => [], 'total' => 0, 'totalItems' => 0]);
    }

    #[Route('/commande/{id}/declarer-incident', name: 'client_commande_declarer_incident', methods: ['POST'])]
    public function declarerIncident(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        
        // Vérifier que $user est bien une instance de User
        if (!$user instanceof User) {
            $this->addFlash('error', 'Utilisateur non identifié.');
            return $this->redirectToRoute('client_commandes');
        }
        
        // Vérifier que c'est bien la commande du client connecté
        if ($commande->getUser() !== $user) {
            $this->addFlash('error', 'Cette commande ne vous appartient pas.');
            return $this->redirectToRoute('client_commandes');
        }
        
        // Vérifier que la commande est terminée (livrée)
        if ($commande->getStatut() !== 'terminee') {
            $this->addFlash('error', 'Vous ne pouvez déclarer un incident que sur une commande livrée.');
            return $this->redirectToRoute('client_commandes');
        }
        
        $description = $request->request->get('description', 'Incident non spécifié');
        $typeIncident = $request->request->get('type_incident', 'autre');
        
        // Changer le statut à "probleme"
        $commande->setStatut('probleme');
        
        // Ajouter la description de l'incident dans les notes
        $notes = $commande->getNotes() ?? '';
        $notes .= "\n\n--- INCIDENT CLIENT ---\n";
        $notes .= "Date: " . date('d/m/Y H:i') . "\n";
        
        // Récupérer les infos de l'utilisateur de manière sécurisée
        $clientEmail = method_exists($user, 'getEmail') ? $user->getEmail() : 'Non disponible';
        $clientPrenom = method_exists($user, 'getPrenom') ? $user->getPrenom() : '';
        $clientNom = method_exists($user, 'getNom') ? $user->getNom() : '';
        
        $notes .= "Client: " . $clientEmail . "\n";
        
        if ($clientPrenom || $clientNom) {
            $notes .= "Nom: " . trim($clientPrenom . ' ' . $clientNom) . "\n";
        }
        
        $notes .= "Type d'incident: " . $typeIncident . "\n";
        $notes .= "Description: " . $description . "\n";
        $commande->setNotes($notes);
        
        $em->flush();
        
        $this->addFlash('success', 'Incident déclaré. Notre équipe va vous contacter rapidement.');
        return $this->redirectToRoute('client_commandes');
    }
}