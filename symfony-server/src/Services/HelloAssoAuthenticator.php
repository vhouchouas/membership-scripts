<?php

namespace App\Services;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;
use App\Models\HelloAssoTokens;

class HelloAssoAuthenticator {
	const OAUTH_URL =  "https://api.helloasso.com/oauth2/token";
	const HTTP_HEADER = ['Content-type' => 'application/x-www-form-urlencoded'];

	private string $tokensFile;
	private ?HelloAssoTokens $latestTokens = null;

	public function __construct(private LoggerInterface $logger, private HttpClientInterface $client, private ContainerBagInterface $params) {
		$this->tokensFile = $params->get("helloasso.tokensFile");
		// Don't refresh the token in the constructor, do it lazily.
		// So if this class instantiated by the DI for a query which won't actually need
		// to query helloasso, then we won't waste time performing queries to refresh our token
	}

	public function getAccessToken(): string {
		if (  $this->latestTokens == null) {
			$this->logger->info("Going to get fresh helloAsso tokens");
			$this->getFreshTokens();
		}
		return $this->latestTokens->getAccessToken();
	}

	private function getFreshTokens() : void {
		// According to Helloasso doc:
		// > you MUST obtain a new access_token using the refresh_token issued to you,
		// > and MUST NOT obtain a new access_token by using the client
		$this->latestTokens = HelloAssoTokens::fromFile($this->tokensFile, $this->logger);
		if ($this->latestTokens != null){
			$this->refreshTokens();
		} else {
			$this->getTokensFromScratch();
		}
	}

	private function refreshTokens(){
		$this->logger->info("Going to use helloasso refresh token");
		/**
		 * For debugging purposes, to do a curl query from CLI:
		 * curl -X POST 'https://api.helloasso.com/oauth2/token' -H 'content-type: application/x-www-form-urlencoded' --data-urlencode 'grant_type=refresh_token' --data-urlencode 'client_id=$CLIENT_ID' --data-urlencode 'refresh_token=$REFRESH_TOKEN'
		 */
		$response = $this->client->request('POST', self::OAUTH_URL, [
			'headers' => self::HTTP_HEADER,
				'body' => [
				"grant_type" => "refresh_token",
				"client_id" => $this->params->get('helloasso.clientId'),
				"refresh_token" => $this->latestTokens->getRefreshToken()
			]
		]);

		if ($response->getStatusCode() == 401 || $response->getStatusCode() == 400) {
			$this->logger->info("Got 4xx when trying to use helloasso refresh token. We try to get brand new ones");
			$this->getTokensFromScratch();
		} else {
			$this->logger->info("Got new helloasso tokens");
			$content = $response->getContent();
			$newTokens = HelloAssoTokens::fromContentInRam($content, $this->logger);
			// It already occured that we received corrupted content from helloasso. We check for this case because
			// we at least want to make sure we don't override the file on the filesystem.
			if ($newTokens == null) {
				$this->logger->error("received invalid tokens from helloasso so we don't overwrite the existing file and we try to get brand new ones. Received: $content");
				$this->getTokensFromScratch();
			} else {
				$this->logger->info("The tokens received seem valid");
				$this->latestTokens = $newTokens;
				$this->writeTokensFile($content);
			}
		}
	}

	private function writeTokensFile(string $rawContent){
		if (!file_exists(dirname($this->tokensFile))) {
			mkdir(dirname($this->tokensFile), 0700, true);
		}
		file_put_contents($this->tokensFile, $rawContent);
	}

	private function getTokensFromScratch() {
		$this->logger->info("Going to get helloasso tokens from scratch");
		/**
		 * For debugging purposes, to do a curl query from CLI:
		 * curl -X POST 'https://api.helloasso.com/oauth2/token' -H 'content-type: application/x-www-form-urlencoded' --data-urlencode 'grant_type=client_credentials' --data-urlencode 'client_id=$CLIENT_ID' --data-urlencode 'client_secret=$CLIENT_SECRET'
		 */
		$response = $this->client->request('POST', self::OAUTH_URL, [
			'headers' => self::HTTP_HEADER,
			'body' => [
				"grant_type" => "client_credentials",
				"client_id" => $this->params->get('helloasso.clientId'),
				"client_secret" => $this->params->get('helloasso.clientSecret')
			]
		]);
		$content = $response->getContent();
		$tokensFromScratch = HelloAssoTokens::fromContentInRam($content, $this->logger);
		if ($tokensFromScratch == null) {
			$this->logger->error("Failed to get tokens from scratch. The rest of the run may fail");
		} else {
			$this->latestTokens = $tokensFromScratch;
			$this->writeTokensFile($content);
		}
	}
}
