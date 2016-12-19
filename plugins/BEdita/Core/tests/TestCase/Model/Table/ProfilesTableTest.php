<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2016 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */

namespace BEdita\Core\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * {@see \BEdita\Core\Model\Table\ProfilesTable} Test Case
 *
 * @coversDefaultClass \BEdita\Core\Model\Table\ProfilesTable
 */
class ProfilesTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \BEdita\Core\Model\Table\ProfilesTable
     */
    public $Profiles;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.BEdita/Core.object_types',
        'plugin.BEdita/Core.objects',
        'plugin.BEdita/Core.profiles',
        'plugin.BEdita/Core.users',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->Profiles = TableRegistry::get('Profiles');
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Profiles);

        parent::tearDown();
    }

    /**
     * Test initialize method
     *
     * @return void
     * @coversNothing
     */
    public function testInitialize()
    {
        $this->Profiles->removeBehavior('ClassTableInheritance');
        $this->Profiles->initialize([]);
        $this->assertEquals('profiles', $this->Profiles->table());
        $this->assertEquals('id', $this->Profiles->primaryKey());
        $this->assertEquals('name', $this->Profiles->displayField());

        $this->assertInstanceOf('\BEdita\Core\ORM\Association\ExtensionOf', $this->Profiles->Objects);
        $this->assertInstanceOf(
            '\BEdita\Core\Model\Behavior\ClassTableInheritanceBehavior',
            $this->Profiles->behaviors()->get('ClassTableInheritance')
        );
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
                    'name' => 'Channelweb Srl',
                    'company' => true,
                    'uname' => 'object-associated-' . md5(microtime()),
                ],
            ],
            'notUniqueEmail' => [
                false,
                [
                    'email' => 'gustavo.supporto@channelweb.it',
                    'object_type_id' => 2,
                    'uname' => 'object-associated-' . md5(microtime()),
                    'status' => 'draft',
                    'lang' => 'eng',
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
     * @coversNothing
     * @covers \BEdita\Core\ORM\Association\ExtensionOf::saveAssociated()
     * @covers \BEdita\Core\ORM\Association\ExtensionOf::targetPropertiesValues()
     */
    public function testValidation($expected, array $data)
    {
        $profile = $this->Profiles->newEntity($data);
        $profile->created_by = 1;
        $profile->modified_by = 1;
        $profile->type = 'profiles';

        $error = (bool)$profile->errors();
        $this->assertEquals($expected, !$error, print_r($profile->errors(), true));

        if ($expected) {
            $success = $this->Profiles->save($profile);
            $this->assertTrue((bool)$success);
        }
    }

    /**
     * Test find method.
     *
     * @return void
     *
     * @coversNothing
     */
    public function testFind()
    {
        $expectedProperties = [
            'id',
            'name',
            'surname',
            'email',
            'person_title',
            'gender',
            'birthdate',
            'deathdate',
            'company',
            'company_name',
            'company_kind',
            'street_address',
            'city',
            'zipcode',
            'country',
            'state_name',
            'phone',
            'website',
            'status',
            'uname',
            'locked',
            'created',
            'modified',
            'published',
            'title',
            'description',
            'body',
            'extra',
            'lang',
            'created_by',
            'modified_by',
            'publish_start',
            'publish_end',
            'type',
        ];

        sort($expectedProperties);

        $profile = $this->Profiles->find()
            ->where(['object_type_id' => 2])
            ->first();
        $visibleProperties = $profile->visibleProperties();
        sort($visibleProperties);

        $this->assertEquals($expectedProperties, $visibleProperties);
    }

    /**
     * Test delete.
     *
     * @return void
     */
    public function testDelete()
    {
        $profile = $this->Profiles->find()->first();
        $id = $profile->id;
        $this->assertEquals(true, $this->Profiles->delete($profile));


        $inheritanceTables = $this->Profiles->inheritedTables(true);
        // remove behavior to avoid auto contain() with inherited tables
        $this->Profiles->removeBehavior('ClassTableInheritance');
        $inheritanceTables[] = $this->Profiles;

        foreach ($inheritanceTables as $table) {
            try {
                $entity = $table->get($id);
                $this->fail(ucfirst($table) . ' record not deleted');
            } catch (\Cake\Datasource\Exception\RecordNotFoundException $ex) {
                continue;
            }
        }
    }
}