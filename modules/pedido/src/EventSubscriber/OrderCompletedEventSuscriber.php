<?php


namespace Drupal\pedido\EventSubscriber;


use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\email\Entity\Mail;
use Drupal\profile\Entity\Profile;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\user\Entity\User;
use Picqer\Carriers\SendCloud\Parcel;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderCompletedEventSuscriber implements EventSubscriberInterface {

    use StringTranslationTrait;

    /**
     * The language manager.
     *
     * @var \Drupal\Core\Language\LanguageManagerInterface
     */
    protected LanguageManagerInterface $languageManager;

    /**
     * The mail manager.
     *
     * @var \Drupal\Core\Mail\MailManagerInterface
     */
    protected MailManagerInterface $mailManager;

    /**
     * @var \Drupal\Core\Logger\LoggerChannel
     */
    private LoggerChannel $logger;

    /**
     * @var \Drupal\Core\Utility\Token
     */
    private Token $token;

    /**
     * @var \Drupal\Core\Render\Renderer
     */
    private Renderer $renderer;

    /**
     * @var \Drupal\Core\Entity\EntityTypeManager
     */
    private EntityTypeManager $entityTypeManager;

    /**
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    private ModuleHandler $moduleHandler;

    /**
     * Constructs a new OrderFulfillmentSubscriber object.
     *
     * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
     *   The language manager.
     * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
     *   The mail manager.
     * @param \Drupal\Core\Logger\LoggerChannel $loggerChannel
     * @param \Drupal\Core\Utility\Token $token
     * @param \Drupal\Core\Render\Renderer $renderer
     * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
     * @param \Drupal\Core\Extension\ModuleHandler $moduleHandler
     */
    public function __construct(
        LanguageManagerInterface $language_manager,
        MailManagerInterface $mail_manager,
        LoggerChannel $loggerChannel,
        Token $token,
        Renderer $renderer,
        EntityTypeManager $entityTypeManager,
        ModuleHandler $moduleHandler,
    ) {
        $this->languageManager = $language_manager;
        $this->mailManager = $mail_manager;
        $this->logger = $loggerChannel;
        $this->token = $token;
        $this->renderer = $renderer;
        $this->entityTypeManager = $entityTypeManager;
        $this->moduleHandler = $moduleHandler;
    }

    public static function getSubscribedEvents(): array {
        return [
            'commerce_order.place.post_transition' => ['orderCompleted', 200],
            'commerce_order.fulfill.post_transition' => ['orderSent', 0],
        ];
    }

    /**
     * Pedido completado.
     *
     * @param WorkflowTransitionEvent $event
     *
     * @throws \Exception
     */
    public function orderCompleted(WorkflowTransitionEvent $event) {
        /** @var \Drupal\commerce_order\Entity\Order $order */
        $order = $event->getEntity();
        $this->sendEmailCompleted($order);
        try {
            $this->setBillingProfile($order);
        }
        catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
            $this->logger->error($e->getMessage());
        }
        $this->setUserInformation($order);
    }

    /**
     * Actualizar datos del usuario.
     *
     * @param \Drupal\commerce_order\Entity\Order $order
     */
    public function setUserInformation(Order $order) {
        $profiles = $order->collectProfiles();

        if (isset($profiles['shipping']) && $profiles['shipping'] instanceof Profile && $order->getCustomerId()) {
            /** @var User $user */
            $user = User::load($order->getCustomerId());
            $user->set('field_nombre', $profiles['shipping']->get('address')->given_name);
            $user->set('field_apellidos', $profiles['shipping']->get('address')->family_name);
            $user->set('field_telefono', $profiles['shipping']->get('field_telefono')->value);
            try {
                $user->save();
            }
            catch (EntityStorageException $e) {
                $this->logger->error($e->getMessage());
            }
        }

    }

    /**
     * Guardar perfil de facturación.
     *
     * @param \Drupal\commerce_order\Entity\Order $order
     *
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public function setBillingProfile(Order $order) {
        $billing_profile = $order->getBillingProfile();
        if ($billing_profile instanceof Profile && $order->getCustomerId()) {
            $address = $billing_profile->get('address')->getValue()[0];
            $dni = $billing_profile->get('field_dni_cif')->value;
            $phone = $billing_profile->get('field_telefono')->value;

            $storage = $this->entityTypeManager->getStorage('profile');
            /** @var User $user */
            $user = User::load($order->getCustomerId());
            $profiles = $storage->loadMultipleByUser($user, 'datos_de_facturacion');

            if (!empty($profiles)) {
                /** @var Profile $profile */
                $profile = current($profiles);
                $profile->set('address', $address);
                $profile->set('field_dni_cif', $dni);
                $profile->set('field_telefono', $phone);
            }
            else {
                $profile = Profile::create([
                    'type' => 'datos_de_facturacion',
                    'uid' => $order->getCustomerId(),
                    'address' => $address,
                    'field_dni_cif' => $dni,
                    'field_telefono' => $phone,
                ]);
            }

            try {
                $profile->save();
            }
            catch (EntityStorageException $e) {
                $this->logger->error($e->getMessage());
            }

        }
    }

    /**
     * Enviar mail pedido completado.
     *
     * @param \Drupal\commerce_order\Entity\Order $order
     *
     * @throws \Exception
     */
    public function sendEmailCompleted(Order $order) {

        if ($customer = $order->getCustomer()) {
            $langcode = $customer->getPreferredLangcode();
        }
        else {
            $langcode = $this->languageManager->getDefaultLanguage()->getId();
        }

        $to = $order->getEmail();

        $profiles = $order->collectProfiles();

        $resumen = [
            '#theme' => 'correo_resumen_pedido',
            '#order_entity' => $order,
        ];

        $resumen = $this->renderer->render($resumen);

        $envio = '';
        if (isset($profiles['shipping']) && $profiles['shipping'] instanceof Profile) {
            $envio = [
                '#theme' => 'correo_informacion_envio',
                '#profile' => $profiles['shipping'],
            ];
            $envio = $this->renderer->render($envio);
        }



        // Correo al cliente
        $mail = Mail::load(Mail::TYPE_CONFIRM_ORDER);
        if ($mail instanceof Mail) {
            $subject = $mail->getSubject();
            $body = $mail->getBody();

            $token_service = $this->token;
            $subject = $token_service->replace($subject, [
                'commerce_order' => $order
            ]);
            $body = $token_service->replace($body, [
                'commerce_order' => $order
            ]);

            $body = str_replace('[resumen]', $resumen, $body);
            $body = str_replace('[datos_envio]', $envio, $body);

            $params = [
                'from' => $order->getStore()->getEmail(),
                'subject' => $subject,
                'body' => ['#markup' => Markup::create($body)],
            ];


            $this->mailManager->mail('commerce', 'receipt', $to, $langcode, $params);
            $this->logger->info('Pedido #' . $order->id() . ' completado por ' . $to);
        }




        // Correo del propietario
        $mail = Mail::load(Mail::TYPE_CONFIRM_ORDER_STORE);
        if ($mail instanceof Mail) {
            $subject = $mail->getSubject();
            $body = $mail->getBody();

            $token_service = $this->token;
            $subject = $token_service->replace($subject, [
                'commerce_order' => $order
            ]);
            $body = $token_service->replace($body, [
                'commerce_order' => $order
            ]);

            $body = str_replace('[resumen]', $resumen, $body);
            $body = str_replace('[datos_envio]', $envio, $body);

            $params = [
                'from' => $order->getStore()->getEmail(),
                'subject' => $subject,
                'body' => ['#markup' => Markup::create($body)],
            ];

            $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => 'propietario']);
            foreach ($users as $user) {
                if ($user instanceof  User) {
                    $to = $user->getEmail();
                    $this->mailManager->mail('commerce', 'admin_order_completed', $user->getEmail(), $langcode, $params);
                    $this->logger->info('Pedido #' . $order->id() . ' completado enviado al propietario ' . $to);
                }
            }
        }

    }

    /**
     * Pedido enviado.
     *
     * @param WorkflowTransitionEvent $event
     * @throws \Exception
     */
    public function orderSent(WorkflowTransitionEvent $event) {
        /** @var \Drupal\commerce_order\Entity\Order $order */
        $order = $event->getEntity();
        $correo = $this->mailOrderSend($order);
        if (!empty($correo)) {
            if ($customer = $order->getCustomer()) {
                $langcode = $customer->getPreferredLangcode();
            }
            else {
                $langcode = $this->languageManager->getDefaultLanguage()->getId();
            }

            $to = $order->getEmail();

            $params = [
                'from' => $order->getStore()->getEmail(),
                'subject' => $correo['asunto'],
                'body' => ['#markup' => Markup::create($correo['body'])],
            ];

            $this->mailManager->mail('commerce', 'receipt', $to, $langcode, $params);
            $this->logger->info('Pedido #' . $order->id() . ' enviado a ' . $to);
        }
    }

    /**
     * Correo pedido enviado.
     *
     * @param \Drupal\commerce_order\Entity\Order $order
     *
     * @return string[]
     * @throws \Exception
     */
    public function mailOrderSend(Order $order) {

        $correo = ['asunto' => 'Pedido enviado', 'body' => 'Pedido enviado'];

        $profiles = $order->collectProfiles();

        $resumen = [
            '#theme' => 'correo_resumen_pedido',
            '#order_entity' => $order,
        ];

        $resumen = $this->renderer->render($resumen);

        $envio = '';
        if (isset($profiles['shipping']) && $profiles['shipping'] instanceof Profile) {
            $envio = [
                '#theme' => 'correo_informacion_envio',
                '#profile' => $profiles['shipping'],
            ];
            $envio = $this->renderer->render($envio);
        }

        $mail = Mail::load(Mail::TYPE_SENT_ORDER);
        if ($mail instanceof Mail) {
            $subject = $mail->getSubject();
            $body = $mail->getBody();

            $token_service = $this->token;
            $subject = $token_service->replace($subject, [
                'commerce_order' => $order
            ]);
            $body = $token_service->replace($body, [
                'commerce_order' => $order
            ]);

            $body = str_replace('[resumen]', $resumen, $body);
            $body = str_replace('[datos_envio]', $envio, $body);

            $moduleHandler = $this->moduleHandler;
            $tracking_url = '';
            if ($moduleHandler->moduleExists('commerce_sendcloud')) {
                $tracking_url = \Drupal::service('commerce_sendcloud.shipment')->getTrackingUrl($order);
                $parcel = \Drupal::service('commerce_sendcloud.shipment')->getParcelOrder($order);
                if ($parcel instanceof Parcel) {
                    $tracking_url = Url::fromRoute('commerce_sendcloud.tracking', ['tracking_number' => $parcel->tracking_number], ['absolute' => TRUE])->toString();
                }
            }

            $body = str_replace('[tracking_url]', $tracking_url, $body);

            $correo['asunto'] = $subject;
            $correo['body'] = $body;
        }

        return $correo;
    }

    /**
     * Crear cliente en Stripe para usuarios anónimos.
     *
     * @param \Drupal\commerce_order\Entity\Order $order
     * @param \Drupal\user\Entity\User $user
     */
    public function addCustomerStripe(Order $order, User $user) {
        $commerce_payment = NULL;
        try {
            $commerce_payment = $this->entityTypeManager->getStorage('commerce_payment')
                ->loadByProperties(['order_id' => $order->id()]);
        }
        catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
            $this->logger->error($e->getMessage());
        }

        if ($commerce_payment) {
            $commerce_payment = reset($commerce_payment);
        }

        if ($commerce_payment instanceof Payment) {
            $remote_id = $commerce_payment->getRemoteId();

            if ($remote_id) {
                $commerce_payment_gateway = NULL;
                try {
                    $commerce_payment_gateway = $this->entityTypeManager->getStorage('commerce_payment_gateway')
                        ->loadByProperties(['plugin' => 'stripe']);
                }
                catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
                    $this->logger->error($e->getMessage());
                }
                $config = NULL;
                if ($commerce_payment_gateway) {
                    $commerce_payment_gateway = reset($commerce_payment_gateway);
                    $plugin = $commerce_payment_gateway->getPlugin();
                    if ($plugin->getPluginId() == 'stripe') {
                        $config = $plugin->getConfiguration();
                    }
                }

                if ($config) {
                    $provider = 'stripe|' . $config['mode'];
                    \Stripe\Stripe::setApiKey($config['secret_key']);
                    $customer = NULL;
                    if ($order->get('field_customer_id')->value) {
                        try {
                            Customer::update($order->get('field_customer_id')->value, ['email' => $order->getEmail()]);
                        }
                        catch (ApiErrorException $e) {
                            $this->logger->error($e->getMessage());
                        }
                        $user->set('commerce_remote_id', ['provider' => $provider, 'remote_id' => $order->get('field_customer_id')->value]);
                        try {
                            $user->save();
                        }
                        catch (EntityStorageException $e) {
                            $this->logger->error($e->getMessage());
                        }
                    }
                }
            }
        }
    }
}
