<?php

namespace ZWP\Members\Services;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;

class HelloAssoConnector {
	const HELLOASSOV5_TOKENS_PATH  = __DIR__ . "/helloassoV5_tokens.json"; 
	const OAUTH_URL =  "https://api.helloasso.com/oauth2/token";
	const HTTP_HEADER = ['Content-type' => 'application/x-www-form-urlencoded'];

	public function __construct(private LoggerInterface $logger, private HttpClientInterface $client, private ContainerBagInterface $params) {
		// An access token has a lifetime of 30 minutes so by refreshing it upon instantiation
		// we ensure the query will have a valid token whenever it needs it.
		// This logic is suboptimal in number of queries to get fresh  but:
		// - it leads to simpler code
		// - since our main endpoint is most of the time called only once per hour, it doesn't matter that much
		$this->ensureTokensOnDiskAreUpToDate();
	}

	private function ensureTokensOnDiskAreUpToDate(){
		// According to Helloasso doc:
		// > you MUST obtain a new access_token using the refresh_token issued to you,
		// > and MUST NOT obtain a new access_token by using the client
		if ($this->isValidTokensFile()){
			$this->refreshTokens();
		} else {
			$this->getTokensFromScratch();
		}
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
		$this->writeTokensFile($response->getContent());
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
				"refresh_token" => $this->parseRefreshToken()
			]
		]);

		if ($response->getStatusCode() == 401) {
			$this->logger->info("Got 401 when trying to use helloasso refresh token. We try to get brand new ones");
			$this->getTokensFromScratch();
		} else {
			$this->logger->info("Got new helloasso tokens");
			$this->writeTokensFile($response->getContent());
		}
	}

	private function parseRefreshToken(){
		$tokens = $this->parseTokensAsArray();
		return $tokens["refresh_token"];
	}

	private function parseTokensAsArray(){
		return json_decode(file_get_contents(self::HELLOASSOV5_TOKENS_PATH), true);
	}

	private function writeTokensFile($content){
		if (!self::isValidTokensJson($content)){
			$this->logger->error("received invalid tokens from helloasso so we don't overwrite the existing file. Received: $content");
			return;
		}

		if (!file_exists(dirname(self::HELLOASSOV5_TOKENS_PATH))) {
			mkdir(dirname(self::HELLOASSOV5_TOKENS_PATH), 0700, true);
		}
		file_put_contents(self::HELLOASSOV5_TOKENS_PATH, $content);
	}

	private function isValidTokensFile(){
		if (!file_exists(self::HELLOASSOV5_TOKENS_PATH)){
			$this->logger->info("The tokens file doesn't exist");
			return false;
		}

		$content = file_get_contents(self::HELLOASSOV5_TOKENS_PATH);
		if (self::isValidTokensJson($content)){
			$this->logger->info("Local tokens file seems ok");
			return true;
		} else {
			$this->logger->error("Local tokens file seems not ok. We delete it. Was: $content");
			unlink(self::HELLOASSOV5_TOKENS_PATH);
			return false;
		}
	}

	public static function isValidTokensJson(string $content){
		$tokens = json_decode($content, true);
		$reasonNotOk = "";
		if (is_null($tokens)){
			$this->logger->error("The content doesn't look like valid json");
			return false;
		} else if (!array_key_exists("access_token", $tokens)){
			$this->logger->error("The content is missing access_token");
			return false;
		} else if (!array_key_exists("refresh_token", $tokens)){
			$this->logger->error("The content is missing refresh_token");
			return false;
		}
		return true;
	}

	public function getAllHelloAssoSubscriptions(\DateTime $from, \DateTime $to){
		// TODO
	}
}
