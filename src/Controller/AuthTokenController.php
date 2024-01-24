<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\AuthToken;
use App\Entity\Users;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use DateTime;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

use App\Custom\Others;

#[Route('/auth', name: 'auth_')]
class AuthTokenController extends AbstractController
{

    /* ===============================================================================================================
    ||  [POST] {url}/auth/login --> Identifie l'utilisateur                                                        ||
    =============================================================================================================== */
    #[Route('/login', name: 'app_auth_token', methods:['post'])]
    public function login(ManagerRegistry $doctrine, Request $request): JsonResponse
    {
        $others = new Others();

        // on vérifie si l'utilisateur est déjà connecté
        if ($request->headers->get('X-AUTH-TOKEN') !== null)
        {
            try {
                if ($this->authenticate($doctrine, $request))
                {
                    // return $this->json('Vous êtes déjà authentifié.e.');
                    $token = $doctrine->getRepository(AuthToken::class)->findOneBy(array('value' => $request->headers->get('X-AUTH-TOKEN')));
                    $this->renew($doctrine,$token);
                    return $this->json($token);
                }
            }
            catch(\Exception $e) {return $this->json($e->getMessage());}
        }
        
        // puis on s'assure que les identifiants fournis correspondent
        try {
            $user = $others->find_user($doctrine,$request->request->get('username'));
            if (gettype($user) == 'string') {throw new \Exception($user);}
        }
        catch(\Exception $e) {return $this->json($e->getMessage());}

        $factory = new PasswordHasherFactory([
            'common' => ['algorithm' => 'bcrypt'],
        ]);
        $hasher = $factory->getPasswordHasher('common');
        $currentPassword = $user->getPassword();
        $res = $hasher->verify($currentPassword, $request->request->get('password'));

        // et si c'est le cas, on crée et renvoie un nouveau token
        if ($res) {
            $token = $this->create($doctrine,$user);
        } else {
            return $this->json('Identifiants invalides', 401);
        }

        return $this->json([$user->getFirmName(),$token->getValue(),$user->isIsAdmin()]);
    }

    /* ===============================================================================================================
    ||  [POST] {url}/auth/disconnect --> Déconnecte l'utilisateur                                                   ||
    =============================================================================================================== */
    #[Route('/disconnect', name: 'app_disconnect', methods:['get'])]
    public function disconnect(ManagerRegistry $doctrine, Request $request): JsonResponse
    {
        if ($request->headers->get('X-AUTH-TOKEN') !== null)
        {
            try {
                if ($this->authenticate($doctrine, $request))
                {
                    $token = $doctrine->getRepository(AuthToken::class)->findOneBy(array('value' => $request->headers->get('X-AUTH-TOKEN')));
                    $this->delete($doctrine,$token);
                }
            }
            catch(\Exception $e) {return $this->json($e->getMessage());}
        }
        return $this->json('Vous êtes déconnecté.e.');
    }

    // invalide tous les jetons en cours de validité pour un utilisateur lors d'un changement de mot de passe ou suppression
    public function disconnectByUser($doctrine, $userId) {
        $tokens = $doctrine->getRepository(AuthToken::class)->findBy(array('user_id' => $userId));
        foreach ($tokens as $token) {
            $this->delete($doctrine,$token);
        }
    }

    // vérifie si la requête est valide
    public function authenticate(ManagerRegistry $doctrine, Request $request)
    {
        $apiToken = $request->headers->get('X-AUTH-TOKEN');
        if (null === $apiToken) {
            throw new CustomUserMessageAuthenticationException('Aucune clé d\'API fournie.');
        }
        $token = $doctrine->getRepository(AuthToken::class)->findOneBy(array('value' => $apiToken));
        if ($token === null) {throw new CustomUserMessageAuthenticationException('Vous n\'êtes pas connecté.e.');}
        if (!$this->checkValidity($doctrine,$token)) {throw new CustomUserMessageAuthenticationException('Votre session a expiré, veuillez vous reconnecter.');}

        return true;
    }

    // vérifie si le token est associé à un compte administrateur
    public function isAdmin(ManagerRegistry $doctrine, Request $request)
    {
        $apiToken = $request->headers->get('X-AUTH-TOKEN');
        if (null === $apiToken) {
            throw new CustomUserMessageAuthenticationException('Aucune clé d\'API fournie.');
        }

        $token = $doctrine->getRepository(AuthToken::class)->findOneBy(array('value' => $apiToken));
        if (!$token->isIsAdmin()) {throw new CustomUserMessageAuthenticationException('Vous ne disposez pas d\'un niveau d\'autorisation suffisant.');}
    }

    // vérifie si un token a expiré
    public function checkValidity(ManagerRegistry $doctrine, AuthToken $token)
    {
        $dateCreated = $token->getDateCreated();
        $time = new DateTime();
        if ($time->getTimestamp() - $dateCreated->getTimestamp() >= $_ENV['AUTH_TOKEN_DECAY']) {
            $this->delete($doctrine, $token);
            return false;
        }
        return true;
    }

    // remet à zéro la durée de validité d'un token
    public function renew(ManagerRegistry $doctrine, AuthToken $token)
    {
        $entityManager = $doctrine->getManager();
        $time = new DateTime();
        $token->setDateCreated($time);

        $entityManager->persist($token);
        $entityManager->flush();
    }

    // supprime un token lorsqu'il a expiré
    public function delete(ManagerRegistry $doctrine, AuthToken $token)
    {
        $entityManager = $doctrine->getManager();
        $entityManager->remove($token);
        $entityManager->flush();
    }

    // crée un nouveau token associé à un utilisateur et l'enregistre
    public function create(ManagerRegistry $doctrine, Users $user): AuthToken
    {
        $entityManager = $doctrine->getManager();
        $token = new AuthToken();

        $time = new DateTime();

        $value = str_replace("\\","!",base64_encode(random_bytes(20)));


        $token->setValue($value);
        $token->setDateCreated($time);
        $token->setUserId($user->getId());
        $token->setIsAdmin($user->isIsAdmin());

        $entityManager->persist($token);
        $entityManager->flush();

        return $token;
    }
}

    
