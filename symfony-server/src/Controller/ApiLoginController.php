<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ApiLoginController extends AbstractController
{
	#[Route('/login', name: 'api_login')]
	public function index(#[CurrentUser] ?User $user, CsrfTokenManagerInterface $csrf): JsonResponse
	{
		if (null === $user) {
			return $this->json(['message' => 'missing credentials'], Response::HTTP_UNAUTHORIZED);
		}
		$response = $this->json([
			'login' => $user->getUserIdentifier(),
		]);

		$cookie = new Cookie('XSRF-TOKEN', $csrf->getToken('id'), strtotime('tomorrow'), null, null, null, false);
		$response->headers->setCookie($cookie);

		return $response;
	}
}
