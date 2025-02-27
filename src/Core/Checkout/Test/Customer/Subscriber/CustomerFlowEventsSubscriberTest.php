<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Test\Flow\FlowActionTestSubscriber;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerFlowEventsSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ?EntityRepositoryInterface $flowRepository;

    private ?EntityRepositoryInterface $customerRepository;

    private ?EventDispatcherInterface $dispatcher;

    private FlowActionTestSubscriber $flowActionTestSubscriber;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->flowActionTestSubscriber = new FlowActionTestSubscriber();

        $this->flowRepository = $this->getContainer()->get('flow.repository');

        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->dispatcher = $this->getContainer()->get('event_dispatcher');

        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->dispatcher->addSubscriber($this->flowActionTestSubscriber);
    }

    public function testTriggerCustomerChangePaymentMethod(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $this->createFlow(CustomerChangedPaymentMethodEvent::EVENT_NAME);

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->setOffset(1)
            ->addFilter(new EqualsFilter('active', true));

        $paymentMethodId = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();

        $this->getContainer()->get('customer.repository')->update([[
            'id' => $customerId,
            'defaultPaymentMethodId' => $paymentMethodId,
        ]], Context::createDefaultContext());

        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_false'] ?? 0);
    }

    public function testTriggerCustomerRegisterEventWhenCustomerCreated(): void
    {
        $this->createFlow(CustomerRegisterEvent::EVENT_NAME);
        $context = Context::createDefaultContext();
        $this->createCustomer($context);

        static::assertEquals(1, $this->flowActionTestSubscriber->actions['unit_test_action_true'] ?? 0);
        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_false'] ?? 0);
    }

    private function createFlow(?string $eventName = null): void
    {
        $sequenceId = Uuid::randomHex();

        $this->flowRepository->create([[
            'name' => 'Create Order',
            'eventName' => $eventName ?? TestFlowBusinessEvent::EVENT_NAME,
            'priority' => 10,
            'active' => true,
            'sequences' => [
                [
                    'id' => $sequenceId,
                    'parentId' => null,
                    'ruleId' => $this->ids->create('ruleId'),
                    'actionName' => null,
                    'config' => [],
                    'position' => 1,
                    'rule' => [
                        'id' => $this->ids->create('ruleId'),
                        'name' => 'Test rule',
                        'priority' => 1,
                        'conditions' => [
                            ['type' => (new AlwaysValidRule())->getName()],
                        ],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => 'unit_test_action_true',
                    'config' => [
                        'tagIds' => [
                            $this->ids->get('tag_id') => 'test tag',
                        ],
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => 'unit_test_action_false',
                    'config' => [
                        'tagIds' => [
                            $this->ids->get('tag_id2') => 'test tag2',
                        ],
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 2,
                    'trueCase' => false,
                ],
            ],
        ],
        ], Context::createDefaultContext());
    }

    private function createCustomer(Context $context): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schoöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schoöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], $context);

        return $customerId;
    }
}
