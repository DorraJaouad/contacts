<?php
/**
 * @copyright Copyright (c) 2020 Matthias Heinisch <nextcloud@matthiasheinisch.de>
 *
 * @author Matthias Heinisch <nextcloud@matthiasheinisch.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Contacts\Service\Social;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;
use OCP\Http\Client\IClientService;
use ChristophWurst\Nextcloud\Testing\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DiasporaProviderTest extends TestCase {
	private $provider;

	/** @var IClientService|MockObject */
	private $clientService;

	/** @var IClient|MockObject */
	private $client;

	/** @var IResponse|MockObject */
	private $response;

	protected function setUp(): void {
		parent::setUp();
		$this->clientService = $this->createMock(IClientService::class);
		$this->response = $this->createMock(IResponse::class);
		$this->client = $this->createMock(IClient::class);

		$this->clientService
			->method('NewClient')
			->willReturn($this->client);

		$this->provider = new DiasporaProvider(
			$this->clientService
		);
	}

	public function dataProviderSupportsContact() {
		$contactWithSocial = [
			'X-SOCIALPROFILE' => [
				['value' => 'one', 'type' => 'diaspora'],
				['value' => 'two', 'type' => 'diaspora']
			]
		];

		$contactWithoutSocial = [
			'X-SOCIALPROFILE' => [
				['value' => 'one', 'type' => 'social2'],
				['value' => 'two', 'type' => 'social1']
			]
		];

		return [
			'contact with diaspora fields' => [$contactWithSocial, true],
			'contact without diaspora fields' => [$contactWithoutSocial, false]
		];
	}

	/**
	 * @dataProvider dataProviderSupportsContact
	 */
	public function testSupportsContact($contact, $expected) {
		$result = $this->provider->supportsContact($contact);
		$this->assertEquals($expected, $result);
	}

	public function dataProviderGetImageUrls() {
		$contactWithSocial = [
			'X-SOCIALPROFILE' => [
				['value' => 'one@two', 'type' => 'diaspora'],
				['value' => 'two@three', 'type' => 'diaspora']
			]
		];
		$contactWithSocialUrls = [
			'https://two/public/one.atom',
			'https://three/public/two.atom'
		];
		$contactWithSocialHtml = array_map(function ($url) {
			return '<logo>'.$url.'-small-avatar.jpg</logo>';
		}, $contactWithSocialUrls);
		$contactWithSocialImg = array_map(function ($url) {
			return $url.'-large-avatar.jpg';
		}, $contactWithSocialUrls);

		$contactWithoutSocial = [
			'X-SOCIALPROFILE' => [
				['value' => 'one', 'type' => 'social2'],
				['value' => 'two', 'type' => 'social1']
			]
		];
		$contactWithoutSocialUrls = [];
		$contactWithoutSocialHtml = [];
		$contactWithoutSocialImg = [];

		return [
			'contact with diaspora fields' => [
				$contactWithSocial,
				$contactWithSocialUrls,
				$contactWithSocialHtml,
				$contactWithSocialImg
			],
			'contact without diaspora fields' => [
				$contactWithoutSocial,
				$contactWithoutSocialUrls,
				$contactWithoutSocialHtml,
				$contactWithoutSocialImg
			]
		];
	}

	/**
	 * @dataProvider dataProviderGetImageUrls
	 */
	public function testGetImageUrls($contact, $urls, $htmls, $imgs) {
		if (count($urls)) {
			$this->response
		  ->method('getBody')
		->willReturnOnConsecutiveCalls(...$htmls);

			$urlArgs = array_map(function ($url) {
				return [$url];
			}, $urls);

			$this->client
		->expects($this->exactly(count($urls)))
			->method('get')
		->withConsecutive(...$urlArgs)
		->willReturn($this->response);
		}

		$result = $this->provider->getImageUrls($contact);
		$this->assertEquals($imgs, $result);
	}

	public function testGetImageUrlLoop() {
		$contact = [
			'X-SOCIALPROFILE' => [
				['value' => 'one@two', 'type' => 'diaspora'],
			]
		];
		$url1 = 'https://two/public/one.atom';
		$url2 = 'https://four/public/three.atom';
		$html1 = '<link rel="alternate" href="'.$url2.'" />';
		$html2 = '<logo>'.$url2.'-small-avatar.jpg</logo>';
		$img = $url2.'-large-avatar.jpg';

		$this->response
		->method('getBody')
	  ->willReturnOnConsecutiveCalls($html1, $html2);

		$this->client
	  ->expects($this->exactly(2))
		->method('get')
	  ->withConsecutive([$url1], [$url2])
	  ->willReturn($this->response);

		$result = $this->provider->getImageUrls($contact);
		$this->assertEquals([$img], $result);
	}
}
