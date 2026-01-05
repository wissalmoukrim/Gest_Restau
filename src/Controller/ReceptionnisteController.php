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

#[Route('/receptionniste')]
#[IsGranted('ROLE_RECEPTIONNISTE')]
class ReceptionnisteController extends AbstractController
{
    #[Route('/', name: 'app_receptionniste')]
    public function index(CommandeRepository $commandeRepo): Response
    {
        // Commandes en attente de prise en charge
        $commandesEnAttente = $commandeRepo->findBy(
            ['statut' => 'en_attente'],
            ['date' => 'ASC']
        );
        
        // Commandes en cours (préparation ou livraison)
        $commandesEnCours = $commandeRepo->createQueryBuilder('c')
            ->where('c.statut IN (:statuts)')
            ->setParameter('statuts', ['en_preparation', 'en_livraison', 'pret_a_livrer'])
            ->orderBy('c.date', 'ASC')
            ->getQuery()
            ->getResult();

        // Commandes terminées aujourd'hui
        $aujourdhui = new \DateTime();
        $aujourdhui->setTime(0, 0, 0);
        
        $commandesTerminees = $commandeRepo->createQueryBuilder('c')
            ->where('c.statut = :statut')
            ->andWhere('c.date >= :date')
            ->setParameter('statut', 'terminee')
            ->setParameter('date', $aujourdhui)
            ->orderBy('c.date', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('receptionniste/index.html.twig', [
            'commandesEnAttente' => $commandesEnAttente,
            'commandesEnCours' => $commandesEnCours,
            'commandesTerminees' => $commandesTerminees,
        ]);
    }

    #[Route('/commande/{id}', name: 'receptionniste_commande_show')]
    public function showCommande(Commande $commande): Response
    {
        return $this->render('receptionniste/commande_show.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/nouvelle-commande', name: 'receptionniste_commande_nouvelle', methods: ['GET', 'POST'])]
    public function nouvelleCommande(Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        if ($request->isMethod('POST')) {
            $user = $this->getUser();
            $userEmail = 'Système';
            
            // Vérification du type
            if ($user instanceof User) {
                $userEmail = $user->getEmail();
            }
            
            // Validation des données
            $total = $request->request->get('total');
            $adresseLivraison = $request->request->get('adresse_livraison');
            $clientEmail = $request->request->get('client_email');
            
            // Validation des champs obligatoires
            if (empty($total) || empty($adresseLivraison) || empty($clientEmail)) {
                $this->addFlash('error', 'Le total, l\'adresse de livraison et le client sont obligatoires.');
                return $this->redirectToRoute('receptionniste_commande_nouvelle');
            }
            
            // Vérifier si le client existe
            $client = $userRepo->findOneBy(['email' => $clientEmail]);
            if (!$client) {
                $this->addFlash('error', 'Client non trouvé. Veuillez vérifier l\'email.');
                return $this->redirectToRoute('receptionniste_commande_nouvelle');
            }
            
            // Créer une nouvelle commande
            $commande = new Commande();
            $commande->setDate(new \DateTime());
            $commande->setStatut('en_attente');
            $commande->setUser($client);
            
            // Convertir et valider le total
            if (!is_numeric($total)) {
                $this->addFlash('error', 'Le total doit être un nombre valide.');
                return $this->redirectToRoute('receptionniste_commande_nouvelle');
            }
            
            // Formatage du total pour le type DECIMAL(10,2)
            $formattedTotal = number_format((float)$total, 2, '.', '');
            $commande->setTotal($formattedTotal);
            
            $commande->setAdresseLivraison($adresseLivraison);
            
            // Ajouter des notes optionnelles
            $notes = $request->request->get('notes', '');
            if (!empty($notes)) {
                $commande->setNotes("Commande créée par réceptionniste: " . $userEmail . "\nNotes: " . $notes);
            } else {
                $commande->setNotes("Commande créée par réceptionniste: " . $userEmail);
            }
            
            try {
                $em->persist($commande);
                $em->flush();
                
                $this->addFlash('success', 'Nouvelle commande #' . $commande->getId() . ' créée avec succès !');
                return $this->redirectToRoute('receptionniste_commande_show', ['id' => $commande->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création de la commande: ' . $e->getMessage());
                return $this->redirectToRoute('receptionniste_commande_nouvelle');
            }
        }

        // Récupérer la liste des clients pour le formulaire
        $clients = $userRepo->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_USER%')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('receptionniste/nouvelle_commande.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/commande/{id}/annuler', name: 'receptionniste_commande_annuler', methods: ['POST'])]
    public function annulerCommande(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        $userEmail = 'Système';
        
        // Vérification du type
        if ($user instanceof User) {
            $userEmail = $user->getEmail();
        }
        
        $raison = $request->request->get('raison', 'Annulée par réceptionniste');
        
        if ($commande->getStatut() === 'en_attente') {
            $commande->setStatut('annulee');
            
            $notes = $commande->getNotes() ?? '';
            $notes .= "\n\n--- ANNULÉE PAR RÉCEPTIONNISTE ---\n";
            $notes .= "Date: " . date('d/m/Y H:i') . "\n";
            $notes .= "Réceptionniste: " . $userEmail . "\n";
            $notes .= "Raison: " . $raison;
            $commande->setNotes($notes);
            
            $em->flush();
            
            $this->addFlash('warning', 'Commande #' . $commande->getId() . ' annulée !');
        } else {
            $this->addFlash('error', 'Seules les commandes en attente peuvent être annulées.');
        }

        return $this->redirectToRoute('app_receptionniste');
    }

    #[Route('/commande/{id}/assigner-chef', name: 'receptionniste_commande_assigner_chef', methods: ['POST'])]
    public function assignerChef(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): Response {
        $user = $this->getUser();
        $userEmail = 'Système';
        
        // Vérification du type
        if ($user instanceof User) {
            $userEmail = $user->getEmail();
        }
        
        $chefId = $request->request->get('chef_id');
        
        if ($chefId && $commande->getStatut() === 'en_attente') {
            $chef = $userRepo->find($chefId);
            if ($chef) {
                $commande->setChef($chef);
                $commande->setStatut('en_preparation');
                
                $notes = $commande->getNotes() ?? '';
                $notes .= "\n\n--- ASSIGNÉE À CHEF ---\n";
                $notes .= "Date: " . date('d/m/Y H:i') . "\n";
                $notes .= "Réceptionniste: " . $userEmail . "\n";
                $notes .= "Chef: " . $chef->getEmail();
                $commande->setNotes($notes);
                
                $em->flush();
                
                $this->addFlash('success', 'Commande #' . $commande->getId() . ' assignée au chef !');
            } else {
                $this->addFlash('error', 'Chef introuvable.');
            }
        } else {
            $this->addFlash('error', 'Seules les commandes en attente peuvent être assignées à un chef.');
        }

        return $this->redirectToRoute('receptionniste_commande_show', ['id' => $commande->getId()]);
    }

    #[Route('/clients', name: 'receptionniste_clients')]
    public function clients(UserRepository $userRepo): Response
    {
        $clients = $userRepo->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_USER%')
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('receptionniste/clients.html.twig', [
            'clients' => $clients,
        ]);
    }
    
    #[Route('/commande/{id}/modifier', name: 'receptionniste_commande_modifier', methods: ['GET', 'POST'])]
    public function modifierCommande(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): Response {
        if ($request->isMethod('POST')) {
            $user = $this->getUser();
            $userEmail = 'Système';
            
            if ($user instanceof User) {
                $userEmail = $user->getEmail();
            }
            
            // Récupérer les données du formulaire
            $total = $request->request->get('total');
            $adresseLivraison = $request->request->get('adresse_livraison');
            $clientEmail = $request->request->get('client_email');
            $notes = $request->request->get('notes');
            $statut = $request->request->get('statut');
            
            // Mettre à jour le total si fourni
            if ($total !== null && is_numeric($total)) {
                $formattedTotal = number_format((float)$total, 2, '.', '');
                $commande->setTotal($formattedTotal);
            }
            
            // Mettre à jour l'adresse si fournie
            if ($adresseLivraison !== null) {
                $commande->setAdresseLivraison($adresseLivraison);
            }
            
            // Mettre à jour le client si fourni
            if ($clientEmail !== null) {
                $client = $userRepo->findOneBy(['email' => $clientEmail]);
                if ($client) {
                    $commande->setUser($client);
                } else {
                    $this->addFlash('error', 'Client non trouvé.');
                }
            }
            
            // Mettre à jour le statut si fourni
            if ($statut !== null && in_array($statut, ['en_attente', 'en_preparation', 'en_livraison', 'terminee', 'annulee'])) {
                $commande->setStatut($statut);
            }
            
            // Ajouter des notes de modification
            if ($notes !== null) {
                $currentNotes = $commande->getNotes() ?? '';
                $currentNotes .= "\n\n--- MODIFIÉ PAR RÉCEPTIONNISTE ---\n";
                $currentNotes .= "Date: " . date('d/m/Y H:i') . "\n";
                $currentNotes .= "Réceptionniste: " . $userEmail . "\n";
                $currentNotes .= "Modifications: " . $notes;
                $commande->setNotes($currentNotes);
            }
            
            try {
                $em->flush();
                $this->addFlash('success', 'Commande #' . $commande->getId() . ' modifiée avec succès !');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification: ' . $e->getMessage());
            }
            
            return $this->redirectToRoute('receptionniste_commande_show', ['id' => $commande->getId()]);
        }
        
        // Récupérer la liste des clients pour le formulaire
        $clients = $userRepo->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_USER%')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
        
        return $this->render('receptionniste/modifier_commande.html.twig', [
            'commande' => $commande,
            'clients' => $clients,
        ]);
    }
    
    #[Route('/commande/{id}/ajouter-note', name: 'receptionniste_commande_ajouter_note', methods: ['POST'])]
    public function ajouterNote(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        $userEmail = 'Système';
        
        if ($user instanceof User) {
            $userEmail = $user->getEmail();
        }
        
        $nouvelleNote = $request->request->get('note');
        
        if (!empty($nouvelleNote)) {
            $notes = $commande->getNotes() ?? '';
            $notes .= "\n\n--- NOTE AJOUTÉE PAR RÉCEPTIONNISTE ---\n";
            $notes .= "Date: " . date('d/m/Y H:i') . "\n";
            $notes .= "Réceptionniste: " . $userEmail . "\n";
            $notes .= "Note: " . $nouvelleNote;
            $commande->setNotes($notes);
            
            $em->flush();
            
            $this->addFlash('info', 'Note ajoutée à la commande #' . $commande->getId());
        }
        
        return $this->redirectToRoute('receptionniste_commande_show', ['id' => $commande->getId()]);
    }
}