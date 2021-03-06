<?php
// src/Controller/profilController.php
namespace App\Controller;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class profilController extends AbstractController
{
    /**
     * @Route("/profil", name="profil")
     */
    public function profil(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {

        $profil = $this->getUser();

        $form = $this->createFormBuilder($profil)
            ->add('username', TextType::class)
            ->add('email', TextType::class)
            ->add('image', FileType::class, [
                'data_class' => null,
                'label' => 'Image',
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'maxSizeMessage' => "Le fichier est trop lourd.",
                        'mimeTypes' => [
                            'image/*'
                        ],
                        'mimeTypesMessage' => "Ce fichier n'est pas une image valide.",
                    ])
                ],
            ])

            ->add('valider', SubmitType::class)
            ->getForm();

        $currentImage = $profil->getImage();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $username = $form->get("username")->getData();
            $Email = $form->get("email")->getData();
            $Image = $form->get("image")->getData();

            // this condition is needed because the 'Image' field is not required
            // so the Image file must be processed only when a file is uploaded

            if ($Image) {

                $originalFilename = pathinfo($Image->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $slugger = new AsciiSlugger();
                $safeFilename = $slugger->slug($originalFilename);

                $newFilename = $safeFilename . '-'. uniqid() .'.' . $Image->guessExtension();
                // Move the file to the directory where Images are stored
                try {
                    
                    $path = $this->getParameter('images_directory').'/'.$currentImage;
                    dump($path);
                    $fs = new Filesystem();
                    $fs->remove($path);

                    $Image->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                // updates the 'ImageFilename' property to store the PDF file name
                // instead of its contents

                $profil->setImage($newFilename);
            }
            $profil->setUsername($username);
            $profil->setEmail($Email);

            $entityManager  = $this->getDoctrine()->getManager();
            $entityManager->persist($profil);
            $entityManager->flush();
        }
        return $this->render('profil/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
