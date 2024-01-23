<?php
namespace App\Custom;

use App\Entity\Users;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;


class Others
{
    // cherche un utilisateur par ID, nom d'entreprise ou email
    public function find_user(ManagerRegistry $doctrine, String $arg) {

        try {
            $user = $doctrine->getRepository(Users::class)->find($arg);
            if (!$user) {
                $user = $doctrine->getRepository(Users::class)->findOneBy(array('firm_name' => $arg));
                if (!$user) {
                    $user = $doctrine->getRepository(Users::class)->findOneBy(array('email' => $arg));
                    if (!$user) {throw new \Exception('Aucun utilisateur trouvé.');}
                }
            }
        }
        catch(\Exception $e) {
            return $e->getMessage();
        }
        return $user;
    }

    // hashe la chaine de caractères passée en argument
    public function custom_hash($plain) {
        $factory = new PasswordHasherFactory([
            'common' => ['algorithm' => 'bcrypt'],
        ]);
        $hasher = $factory->getPasswordHasher('common');
        return $hasher->hash($plain);
    }

    // génère un mot de passe à 6 chiffres
    public function n_digit_random($digits) {
        $temp = "";
      
        for ($i = 0; $i < $digits; $i++) {
          $temp .= rand(0, 9);
        }
      
        return $temp;
    }

    public function convertNum($num) {
        if (substr($num,0,1)=='0') {
            $num = '33'.substr($num,1);
        }
        return $num;
    }

    // envoie un sms au numéro fourni
    public function sendSms($num, $text) {
        // le corps de la fonction est en commentaire parce que chaque appel coûte de l'argent...
        /*
        $curl = curl_init();

        $num = $this->convertNum($num);
 
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.allmysms.com/sms/send",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\r\n    \"from\": \"NOTIMAIL\",\r\n    \"to\": \"".$num."\",\r\n    \"text\": \"".$text."\"\r\n}",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Basic aXJvZGVtYmhjOmU1NTBiYzI2ZTQ2ZjY2Mw==",
                "Content-Type: application/json",
                "cache-control: no-cache"
            ),
        ));
        
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            echo $response;
        }
        */
    }

    // vérifie que le mot de passe correspond au format demandé (actuellement inutilisé)
    public function password_format(String $password)
    {
        return preg_match('/^\d{6}$/', $password);
    }

    // envoi de mail
    public function sendEmail(MailerInterface $mailer, Users $user, String $type, ?String $password = null)
    {
        switch ($type) {
            case 'register':
                $title = 'Création d\'un compte NotiMail';
                break;
            case 'password':
                $title = 'Changement de mot de passe';
                break;
            case 'delete':
                $title = 'Suppression de compte NotiMail';
                break;
            case 'mail':
                $title = 'Nouveau courrier';
                break;
            default:
                $title = 'Contact NotiMail';
        }
        $email = (new TemplatedEmail())
            ->from(new Address('ne-pas-repondre@notimail.fr', 'NOTIMAIL'))
            ->to($user->getEmail())
            //->cc('cc@example.com')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            //->priority(Email::PRIORITY_HIGH)
            ->subject($title)
            ->locale('fr')
            //->text('Sending emails is fun again!')
            ->htmlTemplate('emails/'.$type.'.html.twig')
            ->context([
                'user' => $user,
                'password' => $password
            ]);

        $mailer->send($email);

        // ...
    }
}