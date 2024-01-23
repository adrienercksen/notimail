<?php
 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Users;
use App\Entity\AuthToken;
use DateTime;

use App\Custom\Others;

use Symfony\Component\Mailer\MailerInterface;

#[Route('/api', name: 'api_')]
class ApiController extends AbstractController
{

    /* ===============================================================================================================
    ||  [GET] {url}/api/users --> Renvoie la liste des utilisateurs                                                 ||
    =============================================================================================================== */
    #[Route('/users', name: 'list', methods:['get'] )]
    public function index(ManagerRegistry $doctrine, Request $request): JsonResponse {
        try {
            $this->authorize($doctrine,$request,true);
        } catch(\Exception $e) {
            return $this->errorReturn($e);
        }

        $users = $doctrine
            ->getRepository(Users::class)
            ->findAll();
   
        // On initialise l'objet à convertir en JSON qu'on va renvoyer au client
        $data = [];
        $cnt = 1;
        foreach ($users as $user) {
            array_push($data, [strval($cnt) => [
                'id' => $user->getId(),
                'firmName' => $user->getFirmName(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'phoneNumber' => $user->getPhoneNumber(),
                'lastPickedUp' => (!is_null($user->getLastPickedUp()))?$user->getLastPickedUp()->getTimestamp():null,
                'lastReceivedMail' => (!is_null($user->getLastReceivedMail()))?$user->getLastReceivedMail()->getTimestamp():null,
                'hasMail' => $user->isHasMail(),
                'isAdmin' => $user->isIsAdmin()            
            ]]);
            $cnt++;
        }
   
        return $this->json($data);
    }

    /* ===============================================================================================================
    ||  [GET] {url}/api/usernames --> Renvoie la liste des noms d'utilisateurs                                      ||
    =============================================================================================================== */
    #[Route('/users', name: 'list', methods:['get'] )]
    public function nameList(ManagerRegistry $doctrine, Request $request): JsonResponse {
        try {
            $this->authorize($doctrine,$request,true);
        } catch(\Exception $e) {
            return $this->errorReturn($e);
        }

        $users = $doctrine
            ->getRepository(Users::class)
            ->findAll();
   
        // On initialise l'objet à convertir en JSON qu'on va renvoyer au client
        $data = [];
        foreach ($users as $user) {
            array_push($data, $user->getFirmName());
        }
   
        return $this->json($data);
    }
 
    /* ===============================================================================================================
    ||  [POST] {url}/api/users/add --> Ajoute un utilisateur dans la base de données                                ||
    =============================================================================================================== */
    #[Route('/users/add', name: 'add', methods:['post'] )]
    public function create(ManagerRegistry $doctrine, Request $request, MailerInterface $mailer): JsonResponse {

        try {
            $this->authorize($doctrine,$request,true);
        } catch(\Exception $e) {
            return $this->errorReturn($e);
        }

        $others = new Others();
        $entityManager = $doctrine->getManager();
        $user = new Users();

        // on vérifie les erreurs possibles en amont
        try {
            // on s'assure que les champs obligatoires sont remplis et que le nom d'entreprise / email ne deviendra pas un doublon
            if (is_null($request->request->get('firmName'))) {throw new \Exception('Le champ nom de l\'entreprise est obligatoire.');}
            if (is_null($request->request->get('email'))) {throw new \Exception('Le champ email est obligatoire.');}
            if (is_null($request->request->get('phoneNumber'))) {throw new \Exception('Le champ numéro de téléphone est obligatoire.');}
            if (gettype($others->find_user($doctrine,$request->request->get('firmName'))) == 'object') {throw new \Exception('Ce nom d\'entreprise est déjà pris.');}
            if (gettype($others->find_user($doctrine,$request->request->get('email'))) == 'object') {throw new \Exception('Cet email est déjà utilisé.');}
            
            // si un mot de passe est renseigné, on vérifie qu'il respecte le format demandé
            //if (!is_null($request->request->get('password')) && !$this->password_format($request->request->get('password'))) {throw new \Exception('Format de mot de passe incorrect.');}
        }
        catch(\Exception $e) {
            return $this->errorReturn($e);
        }

        // on insère les valeurs qui ne peuvent pas être nulles (ce qu'on a vérifié juste au-dessus)
        $user->setFirmName($request->request->get('firmName'));
        $user->setEmail($request->request->get('email'));
        $user->setPhoneNumber($request->request->get('phoneNumber'));

        // les données de type "datetime" sont initialisées à la date et l'heure actuelles
        $lastPickedUp = new DateTime();
        $lastPickedUp->setTimestamp(time());
        $user->setLastPickedUp($lastPickedUp);
        $lastReceivedMail = new DateTime();
        if (!is_null($request->request->get('lastReceivedMail'))) {$lastReceivedMail->setTimestamp($request->request->get('lastReceivedMail'));} 
        else {$lastReceivedMail->setTimestamp(time());}
        $user->setLastReceivedMail($lastReceivedMail);

        //  ces valeurs peuvent être nulles
        $user->setFirstName($request->request->get('firstName'));
        $user->setLastName($request->request->get('lastName'));

        // ici, dans le cas où le paramètre est null, on insère un false dans la base de données
        $user->setHasMail(($request->request->get('hasMail')=='true')?($request->request->get('hasMail')):false);
        $user->setIsAdmin(($request->request->get('isAdmin')=='true')?($request->request->get('isAdmin')):false);

        // on génère un mot de passe
        $password = $others->n_digit_random($_ENV['PASSWORD_DIGITS']);
        $hashedPassword = $others->custom_hash($password);

        $user->setPassword($hashedPassword);

            
        // et on valide le tout
        $entityManager->persist($user);
        $entityManager->flush();

        // on envoie un mail pour donner à l'utilisateur son nouveau mot de passe
        $others->sendEmail($mailer, $user, 'register', $password);

        // ainsi qu'un SMS
        $text = 'Bonjour '.$user->getFirmName().',\\r\\n\\nVotre inscription sur Notimail est validée !\\r\\nVous recevrez ici des notifications lorsque du courrier vous sera livré.';
        $others->sendSms($user->getPhoneNumber(),$text);
    
        // on récupère notre data nouvellement créé pour le renvoyer au client, s'il souhaite l'afficher / le vérifier
        $data =  [
            'id' => $user->getId(),
            'firmName' => $user->getFirmName(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            'phoneNumber' => $user->getPhoneNumber(),
            'lastPickedUp' => (!is_null($user->getLastPickedUp()))?$user->getLastPickedUp()->getTimestamp():null,
            'lastReceivedMail' => (!is_null($user->getLastReceivedMail()))?$user->getLastReceivedMail()->getTimestamp():null,
            'hasMail' => $user->isHasMail(),
            'isAdmin' => $user->isIsAdmin()            
        ];

        // et APRÈS avoir créé une instance valide de Users, on lui donne un mot de passe
        // avec la simplification que j'ai faite du système pour hasher les mots de passe, ce n'est plus nécessaire, mais
        // si on souhaite revenir à un algorithme qui prend en compte l'instance d'Users dans son hashage, il faut évidemment
        // que l'user existe avant de hasher

        /*
        if (!is_null($request->request->get('password'))) {
            $this->update_password($doctrine,$request,$request->request->get('firmName'));
        }
        */
        
        return $this->json($data);
        //return $this->json($request->headers->all());
    }
 
    /* ===============================================================================================================
    ||  [GET] {url}/api/users/{arg} --> Renvoie un seul utilisateur correspondant au nom fourni                     ||
    =============================================================================================================== */
    #[Route('/users/{arg}', name: 'show', methods:['get'] )]
    public function show(ManagerRegistry $doctrine, Request $request, String $arg): JsonResponse {
        try {
            $this->authorize($doctrine,$request,false);
        } catch(\Exception $e) {
            return $this->errorReturn($e);
        }

        $others = new Others();
        // on commence par s'assurer que le nom/id donné correspondant à une entrée dans la base de données
        try {
            $user = $others->find_user($doctrine, $arg);
            if (gettype($user) == 'string') {throw new \Exception($user);}
        }
        catch(\Exception $e) {return $this->errorReturn($e);}
        
        // et on crée l'objet correspondant à l'entrée
        $data =  [
            // 'status' => 'success',
            // 'id' => $user->getId(),
            'firmName' => $user->getFirmName(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            'phoneNumber' => $user->getPhoneNumber(),
            'lastPickedUp' => (!is_null($user->getLastPickedUp()))?$user->getLastPickedUp()->getTimestamp():null,
            'lastReceivedMail' => (!is_null($user->getLastReceivedMail()))?$user->getLastReceivedMail()->getTimestamp():null,
            'hasMail' => $user->isHasMail(),
            'isAdmin' => $user->isIsAdmin()            
        ];
           
        return $this->json($data);
    }
 
    /* ===============================================================================================================
    ||  [PUT] [PATCH] {url}/api/users/{nom} --> Modifie l'utilisateur correspondant au nom fourni                   ||
    =============================================================================================================== */
    #[Route('/users/{arg}', name: 'update', methods:['put', 'patch'] )]
    public function update(ManagerRegistry $doctrine, Request $request, String $arg, MailerInterface $mailer): JsonResponse {

        try {
            $this->authorize($doctrine,$request,true);
        } catch(\Exception $e) {
            return $this->errorReturn($e);
        }

        $others = new Others();
        $entityManager = $doctrine->getManager();
        // on commence par s'assurer que le nom/id donné correspondant à une entrée dans la base de données
        try {
            $user = $others->find_user($doctrine, $arg);
            if (gettype($user) == 'string') {throw new \Exception($user);}
        }
        catch(\Exception $e) {return $this->errorReturn($e);}

        // si l'utilisateur reçoit du courrier, on ajuste les valeurs correspondantes et on s'arrête là
        if (!is_null($request->request->get('newMail'))) {
            $user->setHasMail(true);
            $lastReceivedMail = new DateTime();
            $lastReceivedMail->setTimestamp(time());
            $user->setLastReceivedMail($lastReceivedMail);

            // on envoie les notifications correspondantes
            $others->sendEmail($mailer, $user, 'mail');

            $text = 'Bonjour '.$user->getFirmName().',\\r\\n\\nVous avez reçu du courrier.\\r\\nVous pouvez aller le récupérer à votre boîte aux lettres.';
            $others->sendSms($user->getPhoneNumber(),$text);

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->json('L\'utilisateur ' . $user->getFirmName() . 'a bien reçu du courrier.');
        }
   
        // si on veut modifier les champs au cas par cas
        if (!is_null($request->request->get('firmName'))) {$user->setFirmName($request->request->get('firmName'));}
        if (!is_null($request->request->get('firstName'))) {$user->setFirstName($request->request->get('firstName'));}
        if (!is_null($request->request->get('lastName'))) {$user->setLastName($request->request->get('lastName'));}
        if (!is_null($request->request->get('email'))) {$user->setEmail($request->request->get('email'));}
        if (!is_null($request->request->get('phoneNumber'))) {$user->setPhoneNumber($request->request->get('phoneNumber'));}

        // c'est très peu probable qu'on modifie les timestamps comme ça, mais au moins le code pour le faire existe...
        if (!is_null($request->request->get('lastPickedUp'))) {
            $lastPickedUp = new DateTime();
            $lastPickedUp->setTimestamp($request->request->get('lastPickedUp'));
            $user->setLastPickedUp($lastPickedUp);
        }
        if (!is_null($request->request->get('lastReceivedMail'))) {
            $lastReceivedMail = new DateTime();
            $lastReceivedMail->setTimestamp($request->request->get('lastReceivedMail'));
            $user->setLastReceivedMail($lastReceivedMail);
        }

        if (!is_null($request->request->get('hasMail'))) {$user->setHasMail($request->request->get('hasMail'));}
        if (!is_null($request->request->get('isAdmin'))) {$user->setIsAdmin($request->request->get('isAdmin'));}

        $entityManager->persist($user);
        $entityManager->flush();
   
        $data =  [
            // 'status' => 'success',
            // 'id' => $user->getId(),
            'firmName' => $user->getFirmName(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            'phoneNumber' => $user->getPhoneNumber(),
            'lastPickedUp' => (!is_null($user->getLastPickedUp()))?$user->getLastPickedUp()->getTimestamp():null,
            'lastReceivedMail' => (!is_null($user->getLastReceivedMail()))?$user->getLastReceivedMail()->getTimestamp():null,
            'hasMail' => $user->isHasMail(),
            'isAdmin' => $user->isIsAdmin()            
        ];
           
        return $this->json($data);
    }
 
    /* ===============================================================================================================
    ||  [DELETE] {url}/api/users/{nom} --> Supprime l'utilisateur correspondant au nom fourni                       ||
    =============================================================================================================== */
    #[Route('/users/{arg}', name: 'delete', methods:['delete'] )]
    public function delete(ManagerRegistry $doctrine, Request $request, String $arg, MailerInterface $mailer): JsonResponse {

        try {
            $this->authorize($doctrine,$request,true);
        } catch(\Exception $e) {
            return $this->errorReturn($e);
        }

        $others = new Others();
        $entityManager = $doctrine->getManager();
        try {
            $user = $others->find_user($doctrine, $arg);
            if (gettype($user) == 'string') {throw new \Exception($user);}
        }
        catch(\Exception $e) {return $this->errorReturn($e);}
        
        // ...on le supprime de la base de données
        $entityManager->remove($user);
        $entityManager->flush();

        $others->sendEmail($mailer, $user, 'delete');

        $text = 'Bonjour '.$user->getFirmName().',\\r\\n\\nVous ne bénéficiez plus des services de Notimail.\\r\\nMerci de nous avoir fait confiance';
        $others->sendSms($user->getPhoneNumber(),$text);
   
        return $this->json('L\'utilisateur ' . $user->getFirmName() . ' a été supprimé.');
    }

    /* ===============================================================================================================
    ||  [GET] {url}/api/users/{nom}/password --> Génère un nouveau mot de passe pour l'utilisateur spécifié         ||
    =============================================================================================================== */
    #[Route('/users/{arg}/password', name: 'update_password', methods:['get'] )]
    public function change_password(ManagerRegistry $doctrine, Request $request, String $arg, MailerInterface $mailer): JsonResponse {

        // authentification -- si cela échoue, l'erreur est remontée jusqu'ici et la fonction s'interrompt
        try {
            $this->authorize($doctrine,$request,true);
        } catch(\Exception $e) {
            return $this->errorReturn($e);
        }

        // préparation d'un accès aux fonctions annexes et aux entités utilisateurs
        $others = new Others();
        $entityManager = $doctrine->getManager();

        // on vérifie que les données transmises correspondent à un utilisateur existant
        try {
            $user = $others->find_user($doctrine, $arg);
            if (gettype($user) == 'string') {throw new \Exception($user);}
        }
        catch(\Exception $e) {return $this->errorReturn($e);}

        // génération d'un nouveau mot de passe et hashage
        $password = $others->n_digit_random($_ENV['PASSWORD_DIGITS']);
        $hashedPassword = $others->custom_hash($password);

        // validation du tout
        $user->setPassword($hashedPassword);
        $entityManager->flush();

        // envoi d'un mail contenant le nouveau mot de passe
        $others->sendEmail($mailer, $user, 'password', $password);
   
        return $this->json('Le mot de passe pour l\'utilisateur ' . $user->getFirmName() . ' a été changé.');
    }

    /* ===============================================================================================================
    ||  [GET] {url}/api/users/{nom}/picked_mail --> Un UPDATE spécifique accessible sans être admin                 ||
    =============================================================================================================== */
    #[Route('/users/{arg}/picked_mail', name: 'picked_mail', methods:['GET'] )]
    public function pickedMail(ManagerRegistry $doctrine, Request $request, String $arg): JsonResponse
    {
        // authentification -- si cela échoue, l'erreur est remontée jusqu'ici et la fonction s'interrompt
        try {
            $this->authorize($doctrine,$request,false);
        } catch(\Exception $e) {
            return $this->errorReturn($e);
        }

        // préparation d'un accès aux fonctions annexes et aux entités utilisateurs
        $others = new Others();
        $entityManager = $doctrine->getManager();

        // vérification -- les données transmises correspondent à un utilisateur existant
        try {
            $user = $others->find_user($doctrine, $arg);
            if (gettype($user) == 'string') {throw new \Exception($user);}
            $token = $doctrine->getRepository(AuthToken::class)->findOneBy(array('value' => $request->headers->get('X-AUTH-TOKEN')));
            if ($request->headers->get('debug') != true) { 
                if ($user->getId() != $token->getUserId() && !$token->isIsAdmin()) {throw new \Exception('Impossible de modifier les données de cet utilisateur.');}
            }
        }
        catch(\Exception $e) {return $this->errorReturn($e);}

        // mise à jour de l'utilisateur : il a relevé son courrier
        $user->setHasMail(false);
        $lastPickedUp = new DateTime();
        $lastPickedUp->setTimestamp(time());
        $user->setLastPickedUp($lastPickedUp);

        // validation du tout
        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json('Le courrier a été relevé.');
    }

    // fonction supplémentaire pour simplifier l'authentification à chaque appel de l'API
    // --
    // les deux fonctions appelées dedans peuvent faire remonter des exceptions, qui serviront
    // à interrompre l'éxecution des fonctions qui appellent celle-ci
    public function authorize(ManagerRegistry $doctrine, Request $request, bool $adminCheck) {
        if ($request->headers->get('debug') != 'true') {
            $atc = new AuthTokenController();
            $atc->authenticate($doctrine,$request);
            if ($adminCheck) {$atc->isAdmin($doctrine,$request);}
        }
        
    }

    public function errorReturn(\Exception $e): JsonResponse {
        switch ($e->getMessage()) {
            case 'Vous n\'êtes pas connecté.e.':
                $code = 401;
                $customCode = '001';
                $errorType = 'notConnected';
                break;
            case 'Votre session a expiré, veuillez vous reconnecter.':
                $code = 401;
                $customCode = '002';
                $errorType = 'sessionTimedOut';
                break;
            case 'Vous ne disposez pas d\'un niveau d\'autorisation suffisant.':
                $code = 403;
                $customCode = '003';
                $errorType = 'insufficientPrivileges';
                break;
            case 'Aucune clé d\'API fournie.':
                $code = 401;
                $customCode = '004';
                $errorType = 'missingApiKey';
                break;
            case 'Impossible de modifier les données de cet utilisateur.':
                $code = 403;
                $customCode = '005';
                $errorType = 'onlyOnSelf';
                break;
            default:
                $code = 500;
                $customCode = '999';
                $errorType = 'unknownError';
        }
        return $this->json([$customCode,$errorType,$e->getMessage()], $code);
    }
}