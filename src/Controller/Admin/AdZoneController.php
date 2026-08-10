<?php
// src/Controller/Admin/AdZoneController.php

namespace App\Controller\Admin;

use App\Entity\Admin\AdZone;
use App\Form\AdZoneType;
use App\Repository\AdZoneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ad-zones')]
#[IsGranted('ROLE_ADMIN')]
class AdZoneController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/', name: 'admin_ad_zone_index')]
    public function index(AdZoneRepository $repo): Response
    {
        return $this->render('admin/ad/zone/index.html.twig', [
            'zones' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_ad_zone_new')]
    public function new(Request $request): Response
    {
        $zone = new AdZone();
        $form = $this->createForm(AdZoneType::class, $zone);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($zone);
            $this->em->flush();
            return $this->redirectToRoute('admin_ad_zone_index');
        }
        return $this->render('admin/ad/zone/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_ad_zone_edit')]
    public function edit(AdZone $zone, Request $request): Response
    {
        $form = $this->createForm(AdZoneType::class, $zone);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            return $this->redirectToRoute('admin_ad_zone_index');
        }
        return $this->render('admin/ad/zone/edit.html.twig', [
            'form' => $form,
            'zone' => $zone,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_ad_zone_delete', methods: ['POST'])]
    public function delete(AdZone $zone): Response
    {
        $this->em->remove($zone);
        $this->em->flush();
        return $this->redirectToRoute('admin_ad_zone_index');
    }
}