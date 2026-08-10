<?php
// src/Controller/Admin/AdController.php

namespace App\Controller\Admin;

use App\Entity\Admin\Ad;
use App\Form\AdType;
use App\Repository\AdRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ads')]
#[IsGranted('ROLE_ADMIN')]
class AdController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/', name: 'admin_ad_index')]
    public function index(AdRepository $repo): Response
    {
        return $this->render('admin/ad/index.html.twig', ['ads' => $repo->findAll()]);
    }

    #[Route('/new', name: 'admin_ad_new')]
    public function new(Request $request): Response
    {
        $ad = new Ad();
        $form = $this->createForm(AdType::class, $ad);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($ad);
            $this->em->flush();
            return $this->redirectToRoute('admin_ad_index');
        }
        return $this->render('admin/ad/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_ad_edit')]
    public function edit(Ad $ad, Request $request): Response
    {
        $form = $this->createForm(AdType::class, $ad);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            return $this->redirectToRoute('admin_ad_index');
        }
        return $this->render('admin/ad/edit.html.twig', ['form' => $form, 'ad' => $ad]);
    }

    #[Route('/{id}/delete', name: 'admin_ad_delete', methods: ['POST'])]
    public function delete(Ad $ad): Response
    {
        $this->em->remove($ad);
        $this->em->flush();
        return $this->redirectToRoute('admin_ad_index');
    }
}