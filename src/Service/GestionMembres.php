<?php

namespace App\Service;

use App\Entity\Membre;
use App\Entity\Role;
use App\Entity\Societe;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Comptes utilisateurs et rattachement aux sociétés (RH, comptable, trésorier...).
 * L'administration des succursales est gérée par GestionSuccursales.
 */
class GestionMembres
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    /**
     * Retourne le compte existant pour cet e-mail, ou le crée avec un mot de passe provisoire.
     * Ne fait pas de flush.
     *
     * @return array{user: User, motDePasse: ?string} motDePasse renseigné si le compte a été créé
     *
     * @throws \DomainException
     */
    public function obtenirUtilisateur(string $email, ?string $prenoms = null, ?string $nom = null, ?string $telephone = null): array
    {
        $email = mb_strtolower(trim($email));
        $user = $this->users->findOneBy(['email' => $email]);
        if (null !== $user) {
            return ['user' => $user, 'motDePasse' => null];
        }

        if (!$prenoms || !$nom) {
            throw new \DomainException('Aucun compte n\'existe avec cet e-mail : renseignez le prénom et le nom pour le créer.');
        }

        $motDePasse = $this->genererMotDePasse();
        $user = (new User())
            ->setEmail($email)
            ->setPrenoms($prenoms)
            ->setNom($nom)
            ->setTelephone($telephone)
            ->setDoitChangerMotDePasse(true);
        $user->setPassword($this->hasher->hashPassword($user, $motDePasse));
        $this->em->persist($user);

        return ['user' => $user, 'motDePasse' => $motDePasse];
    }

    /**
     * Rattache un utilisateur (existant ou créé) à la société avec le rôle donné.
     *
     * @return array{membre: Membre, motDePasse: ?string}
     *
     * @throws \DomainException
     */
    public function ajouter(Societe $societe, string $email, Role $role, ?string $prenoms = null, ?string $nom = null, ?string $telephone = null): array
    {
        if ($role->getSociete() !== $societe) {
            throw new \DomainException('Ce rôle n\'appartient pas à la société.');
        }

        ['user' => $user, 'motDePasse' => $motDePasse] = $this->obtenirUtilisateur($email, $prenoms, $nom, $telephone);
        if (null !== $user->getMembrePour($societe)) {
            throw new \DomainException(sprintf('%s est déjà membre de cette société.', $user->getNomComplet()));
        }
        if ($user->administre($societe)) {
            throw new \DomainException(sprintf('%s administre déjà la succursale de cette société : il/elle y a déjà tous les droits.', $user->getNomComplet()));
        }

        $membre = new Membre($user, $societe, $role);
        $user->addMembre($membre);
        $societe->addMembre($membre);
        $this->em->persist($membre);
        $this->em->flush();

        return ['membre' => $membre, 'motDePasse' => $motDePasse];
    }

    /** @throws \DomainException */
    public function changerRole(Membre $membre, Role $role): void
    {
        if ($role->getSociete() !== $membre->getSociete()) {
            throw new \DomainException('Ce rôle n\'appartient pas à la société.');
        }

        $membre->setRole($role);
        $this->em->flush();
    }

    /** @throws \DomainException */
    public function basculerStatut(Membre $membre, User $auteur): void
    {
        if ($membre->getUser() === $auteur) {
            throw new \DomainException('Vous ne pouvez pas modifier votre propre accès.');
        }

        $membre->setActif(!$membre->isActif());
        $this->em->flush();
    }

    /** @throws \DomainException */
    public function retirer(Membre $membre, User $auteur): void
    {
        if ($membre->getUser() === $auteur) {
            throw new \DomainException('Vous ne pouvez pas vous retirer vous-même de la société.');
        }

        $membre->getUser()->getMembres()->removeElement($membre);
        $membre->getSociete()->getMembres()->removeElement($membre);
        $this->em->remove($membre);
        $this->em->flush();
    }

    /** Génère et enregistre un nouveau mot de passe provisoire. */
    public function reinitialiserMotDePasse(User $user): string
    {
        $motDePasse = $this->genererMotDePasse();
        $user->setPassword($this->hasher->hashPassword($user, $motDePasse));
        $user->setDoitChangerMotDePasse(true);
        $this->em->flush();

        return $motDePasse;
    }

    private function genererMotDePasse(): string
    {
        // Sans caractères ambigus (0/O, 1/l/I) pour faciliter la transmission.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $motDePasse = '';
        for ($i = 0; $i < 12; ++$i) {
            $motDePasse .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $motDePasse;
    }
}
