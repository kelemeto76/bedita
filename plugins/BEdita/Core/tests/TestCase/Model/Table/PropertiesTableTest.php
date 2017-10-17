<?php
namespace BEdita\Core\Test\TestCase\Model\Table;

use BEdita\Core\Exception\BadFilterException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * {@see \BEdita\Core\Model\Table\PropertiesTable} Test Case
 *
 * @coversDefaultClass \BEdita\Core\Model\Table\PropertiesTable
 */
class PropertiesTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \BEdita\Core\Model\Table\PropertiesTable
     */
    public $Properties;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.BEdita/Core.property_types',
        'plugin.BEdita/Core.object_types',
        'plugin.BEdita/Core.relations',
        'plugin.BEdita/Core.relation_types',
        'plugin.BEdita/Core.properties',
        'plugin.BEdita/Core.objects',
        'plugin.BEdita/Core.profiles',
        'plugin.BEdita/Core.users',
        'plugin.BEdita/Core.locations',
        'plugin.BEdita/Core.media',
    ];

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->Properties = TableRegistry::get('Properties');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        unset($this->Properties);

        parent::tearDown();
    }

    /**
     * Test initialization.
     *
     * @return void
     * @coversNothing
     */
    public function testInitialization()
    {
        $this->Properties->initialize([]);
        static::assertEquals('properties', $this->Properties->getTable());
        static::assertEquals('id', $this->Properties->getPrimaryKey());
        static::assertEquals('name', $this->Properties->getDisplayField());

        static::assertInstanceOf('\Cake\ORM\Association\BelongsTo', $this->Properties->ObjectTypes);
        static::assertInstanceOf('\Cake\ORM\Association\BelongsTo', $this->Properties->PropertyTypes);
        static::assertInstanceOf('\Cake\ORM\Behavior\TimestampBehavior', $this->Properties->behaviors()->get('Timestamp'));
    }

    /**
     * Data provider for `testValidation` test case.
     *
     * @return array
     */
    public function validationProvider()
    {
        return [
            'valid' => [
                true,
                [
                    'name' => 'long_body',
                    'description' => 'long text of a document',
                ],
            ],
            'emptyName' => [
                false,
                [
                    'name' => '',
                    'description' => 'another description',
                ],
            ],
        ];
    }

    /**
     * Test validation.
     *
     * @param bool $expected Expected result.
     * @param array $data Data to be validated.
     *
     * @return void
     * @dataProvider validationProvider
     */
    public function testValidation($expected, array $data)
    {
        $property = $this->Properties->newEntity();
        $this->Properties->patchEntity($property, $data);
        $property->object_type_id = 1;
        $property->property_type_id = 1;
        $property->property = 'string';

        $error = (bool)$property->getErrors();
        static::assertEquals($expected, !$error);

        if ($expected) {
            $success = $this->Properties->save($property);
            static::assertTrue((bool)$success);
        }
    }

    /**
     * Data provider for `testFindObjectType` test case.
     *
     * @return array
     */
    public function findObjectTypeProvider()
    {
        $objects = [
            'uname',
            'status',
            'published',
            'lang',
            'locked',

            'title',
            'description',
            'body',
            'extra',

            'publish_start',
            'publish_end',
            'created',
            'modified',
            'created_by',
            'modified_by',
        ];
        $media = [
            'name',

            'provider',
            'provider_uid',
            'provider_url',
            'provider_thumbnail',
            'provider_extra',
        ];

        return [
            'objects' => [
                $objects,
                ['objects'],
            ],
            'documents' => [
                array_merge(
                    $objects,
                    [ // Documents custom properties.
                        'another_title',
                        'another_description',
                    ]
                ),
                ['documents'],
            ],
            'media' => [
                array_merge(
                    $objects,
                    $media
                ),
                ['media'],
            ],
            'too few' => [
                new BadFilterException(__d('bedita', 'Missing object type to get properties for')),
                [],
            ],
            'too many' => [
                new BadFilterException(__d('bedita', 'Missing object type to get properties for')),
                ['gustavo', 'supporto'],
            ],
        ];
    }

    /**
     * Test finder by object type.
     *
     * @param array|\Exception $expected List of expected properties names.
     * @param array $options Options to be passed to finder.
     * @return void
     *
     * @dataProvider findObjectTypeProvider()
     * @covers ::findObjectType()
     */
    public function testFindObjectType($expected, array $options)
    {
        if ($expected instanceof \Exception) {
            $this->expectException(get_class($expected));
            $this->expectExceptionCode($expected->getCode());
            $this->expectExceptionMessage($expected->getMessage());
        }

        $result = $this->Properties->find('objectType', $options)
            ->extract('name')
            ->toList();

        static::assertEquals($expected, $result, '', 0, 10, true);
    }
}
