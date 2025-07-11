<?php

namespace Drupal\Tests\ilas_chatbot\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ilas_chatbot\Controller\ChatbotController;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Access\CsrfTokenGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\HeaderBag;

/**
 * Tests for ChatbotController.
 *
 * @group ilas_chatbot
 */
class ChatbotControllerTest extends UnitTestCase {

  /**
   * The mocked config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The mocked CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $csrfToken;

  /**
   * The controller under test.
   *
   * @var \Drupal\ilas_chatbot\Controller\ChatbotController
   */
  protected $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the config factory.
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->csrfToken = $this->createMock(CsrfTokenGenerator::class);

    // Create the controller.
    $this->controller = new ChatbotController($this->csrfToken);
    $this->controller->setConfigFactory($this->configFactory);
  }

  /**
   * Tests getFormConfig with valid form type.
   */
  public function testGetFormConfigValidType() {
    // Mock config.
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['form_mappings', [], ['eviction' => '/form/eviction']],
        ['form_titles', [], ['eviction' => 'Eviction Form']],
        ['form_descriptions', [], ['eviction' => 'Help with eviction']],
      ]);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ilas_chatbot.settings')
      ->willReturn($config);

    $response = $this->controller->getFormConfig('eviction');
    $content = json_decode($response->getContent(), TRUE);

    $this->assertEquals('eviction', $content['form_type']);
    $this->assertEquals('Eviction Form', $content['title']);
    $this->assertEquals('Help with eviction', $content['description']);
    $this->assertEquals('/form/eviction', $content['url']);
  }

  /**
   * Tests getFormConfig with invalid form type.
   */
  public function testGetFormConfigInvalidType() {
    $response = $this->controller->getFormConfig('invalid-form!');
    
    $this->assertEquals(400, $response->getStatusCode());
    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals('Invalid form type', $content['error']);
  }

  /**
   * Tests webhook authentication with valid token.
   */
  public function testWebhookAuthValidToken() {
    // Mock config.
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['webhook_secret', NULL, 'test-secret-token'],
        ['webhook_allowed_ips', [], []],
      ]);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ilas_chatbot.settings')
      ->willReturn($config);

    // Create request with valid auth header.
    $request = new Request();
    $request->headers = new HeaderBag([
      'Authorization' => 'Bearer test-secret-token',
      'Content-Type' => 'application/json',
    ]);
    $request->initialize([], [], [], [], [], [], json_encode([
      'queryResult' => ['intent' => ['displayName' => 'test']],
    ]));

    $response = $this->controller->webhook($request);
    
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Tests webhook authentication with invalid token.
   */
  public function testWebhookAuthInvalidToken() {
    // Mock config.
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['webhook_secret', NULL, 'test-secret-token'],
        ['webhook_allowed_ips', [], []],
      ]);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ilas_chatbot.settings')
      ->willReturn($config);

    // Create request with invalid auth header.
    $request = new Request();
    $request->headers = new HeaderBag([
      'Authorization' => 'Bearer wrong-token',
      'Content-Type' => 'application/json',
    ]);

    $response = $this->controller->webhook($request);
    
    $this->assertEquals(401, $response->getStatusCode());
    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals('Unauthorized', $content['error']);
  }

  /**
   * Tests sanitizeParameters method.
   */
  public function testSanitizeParameters() {
    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('sanitizeParameters');
    $method->setAccessible(TRUE);

    $input = [
      'text' => '<script>alert("XSS")</script>',
      'safe' => 'Normal text',
      'nested' => [
        'html' => '<b>Bold</b>',
      ],
    ];

    $result = $method->invoke($this->controller, $input);

    $this->assertEquals('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', $result['text']);
    $this->assertEquals('Normal text', $result['safe']);
    $this->assertEquals('&lt;b&gt;Bold&lt;/b&gt;', $result['nested']['html']);
  }

}