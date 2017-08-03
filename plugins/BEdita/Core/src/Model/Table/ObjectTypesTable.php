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

namespace BEdita\Core\Model\Table;

use BEdita\Core\ORM\Rule\IsUniqueAmongst;
use Cake\Cache\Cache;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Schema\TableSchema;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Event\Event;
use Cake\ORM\Entity;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;

/**
 * ObjectTypes Model
 *
 * @property \Cake\ORM\Association\HasMany $Objects
 * @property \Cake\ORM\Association\HasMany $Properties
 * @property \Cake\ORM\Association\BelongsToMany $LeftRelations
 * @property \Cake\ORM\Association\BelongsToMany $RightRelations
 *
 * @method \BEdita\Core\Model\Entity\ObjectType newEntity($data = null, array $options = [])
 * @method \BEdita\Core\Model\Entity\ObjectType[] newEntities(array $data, array $options = [])
 * @method \BEdita\Core\Model\Entity\ObjectType|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \BEdita\Core\Model\Entity\ObjectType patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \BEdita\Core\Model\Entity\ObjectType[] patchEntities($entities, array $data, array $options = [])
 * @method \BEdita\Core\Model\Entity\ObjectType findOrCreate($search, callable $callback = null, $options = [])
 *
 * @since 4.0.0
 */
class ObjectTypesTable extends Table
{
    /**
     * Cache config name for object types.
     *
     * @var string
     */
    const CACHE_CONFIG = '_bedita_object_types_';

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('object_types');
        $this->setPrimaryKey('id');
        $this->setDisplayField('name');

        $this->hasMany('Objects', [
            'foreignKey' => 'object_type_id',
            'className' => 'Objects',
        ]);

        $this->hasMany('Properties', [
            'foreignKey' => 'property_type_id',
            'className' => 'Properties',
        ]);

        $through = TableRegistry::get('LeftRelationTypes', ['className' => 'RelationTypes']);
        $this->belongsToMany('LeftRelations', [
            'className' => 'Relations',
            'through' => $through->getRegistryAlias(),
            'foreignKey' => 'object_type_id',
            'targetForeignKey' => 'relation_id',
            'conditions' => [
                $through->aliasField('side') => 'left',
            ],
            'cascadeCallbacks' => true,
        ]);
        $through = TableRegistry::get('RightRelationTypes', ['className' => 'RelationTypes']);
        $this->belongsToMany('RightRelations', [
            'className' => 'Relations',
            'through' => $through->getRegistryAlias(),
            'foreignKey' => 'object_type_id',
            'targetForeignKey' => 'relation_id',
            'conditions' => [
                $through->aliasField('side') => 'right',
            ],
            'cascadeCallbacks' => true,
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('name', 'create')
            ->notEmpty('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->allowEmpty('singular')
            ->add('singular', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->allowEmpty('description');

        $validator
            ->requirePresence('plugin', 'create')
            ->notEmpty('plugin');

        $validator
            ->requirePresence('model', 'create')
            ->notEmpty('model');

        $validator
            ->allowEmpty('associations');

        return $validator;
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules
            ->add(new IsUniqueAmongst(['name' => ['name', 'singular']]), '_isUniqueAmongst', [
                'errorField' => 'name',
                'message' => __d('cake', 'This value is already in use'),
            ])
            ->add(new IsUniqueAmongst(['singular' => ['name', 'singular']]), '_isUniqueAmongst', [
                'errorField' => 'singular',
                'message' => __d('cake', 'This value is already in use'),
            ]);

        return $rules;
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    protected function _initializeSchema(TableSchema $schema)
    {
        $schema->columnType('associations', 'json');

        return $schema;
    }

    /**
     * {@inheritDoc}
     *
     * @return \BEdita\Core\Model\Entity\ObjectType
     */
    public function get($primaryKey, $options = [])
    {
        if (is_string($primaryKey) && !is_numeric($primaryKey)) {
            $allTypes = array_flip(
                $this->find('list')
                    ->cache('map', self::CACHE_CONFIG)
                    ->toArray()
            );
            $allTypes += array_flip(
                $this->find('list', ['valueField' => 'singular'])
                    ->cache('map_singular', self::CACHE_CONFIG)
                    ->toArray()
            );

            $primaryKey = Inflector::underscore($primaryKey);
            if (!isset($allTypes[$primaryKey])) {
                throw new RecordNotFoundException(sprintf(
                    'Record not found in table "%s"',
                    $this->getTable()
                ));
            }

            $primaryKey = $allTypes[$primaryKey];
        }

        if (empty($options)) {
            $options = [
                'key' => self::getCacheKey($primaryKey),
                'cache' => self::CACHE_CONFIG,
                'contain' => ['LeftRelations.RightObjectTypes', 'RightRelations.LeftObjectTypes'],
            ];
        }

        return parent::get($primaryKey, $options);
    }

    /**
     * Get cache key name for an object type.
     *
     * @param int $id Object type ID.
     * @return string
     */
    public static function getCacheKey($id)
    {
        return sprintf('id_%d_rel', $id);
    }

    /**
     * Invalidate cache after saving an object type.
     *
     * @param \Cake\Event\Event $event Triggered event.
     * @param \Cake\ORM\Entity $entity Subject entity.
     * @return void
     */
    public function afterSave(Event $event, Entity $entity)
    {
        Cache::delete(self::getCacheKey($entity->id), self::CACHE_CONFIG);
        if ($entity->isDirty('name')) {
            Cache::delete('map', self::CACHE_CONFIG);
        }
        if ($entity->isDirty('singular')) {
            Cache::delete('map_singular', self::CACHE_CONFIG);
        }

        // Invalidate cache for all object types that can be related to this.
        $ids = $this->LeftRelations->junction()
            ->find('list', [
                'keyField' => $this->LeftRelations->getForeignKey(),
                'valueField' => $this->LeftRelations->getForeignKey(),
            ])
            ->where(function (QueryExpression $exp) use ($entity) {
                return $exp->in(
                    $this->LeftRelations->getTargetForeignKey(),
                    $this->LeftRelations->junction()->find()
                        ->select((array)$this->LeftRelations->getTargetForeignKey())
                        ->where([
                            $this->LeftRelations->getForeignKey() => $entity->id,
                        ])
                );
            });
        foreach ($ids as $id) {
            Cache::delete(ObjectTypesTable::getCacheKey($id), ObjectTypesTable::CACHE_CONFIG);
        }
    }

    /**
     * Invalidate cache after deleting an object type.
     *
     * @param \Cake\Event\Event $event Triggered event.
     * @param \Cake\ORM\Entity $entity Subject entity.
     * @return void
     */
    public function afterDelete(Event $event, Entity $entity)
    {
        Cache::delete(self::getCacheKey($entity->id), self::CACHE_CONFIG);
        Cache::delete('map', self::CACHE_CONFIG);
        Cache::delete('map_singular', self::CACHE_CONFIG);
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(Query $query, array $options)
    {
        return $query->contain(['LeftRelations', 'RightRelations']);
    }

    /**
     * Find allowed object types by relation name and side.
     *
     * This finder returns a list of object types that are allowed for the
     * relation specified by the required option `name`. You can specify the
     * side of the relation you want to retrieve allowed object types for by
     * passing an additional option `side` (default: `'right'`).
     *
     * If the specified relation name is actually the name of an inverse relation,
     * this finder automatically takes care of "swapping" sides, always returning
     * correct results.
     *
     * ### Example
     *
     * ```php
     * // Find object types allowed on the "right" side:
     * TableRegistry::get('ObjectTypes')
     *     ->find('byRelation', ['name' => 'my_relation']);
     *
     * // Find a list of object type names allowed on the "left" side of the inverse relation:
     * TableRegistry::get('ObjectTypes')
     *     ->find('byRelation', ['name' => 'my_inverse_relation', 'side' => 'left'])
     *     ->find('list')
     *     ->toArray();
     * ```
     *
     * @param \Cake\ORM\Query $query Query object.
     * @param array $options Additional options. The `name` key is required, while `side` is optional
     *      and assumed to be `'right'` by default.
     * @return \Cake\ORM\Query
     */
    protected function findByRelation(Query $query, array $options = [])
    {
        if (empty($options['name'])) {
            throw new \LogicException(__d('bedita', 'Missing required parameter "{0}"', 'name'));
        }
        $name = Inflector::underscore($options['name']);

        $leftField = 'inverse_name';
        $rightField = 'name';
        if (!empty($options['side']) && $options['side'] !== 'right') {
            $leftField = 'name';
            $rightField = 'inverse_name';
        }

        return $query
            ->distinct()
            ->leftJoinWith('LeftRelations', function (Query $query) use ($name, $leftField) {
                return $query->where(function (QueryExpression $exp) use ($name, $leftField) {
                    return $exp->eq($this->LeftRelations->aliasField($leftField), $name);
                });
            })
            ->leftJoinWith('RightRelations', function (Query $query) use ($name, $rightField) {
                return $query->where(function (QueryExpression $exp) use ($name, $rightField) {
                    return $exp->eq($this->RightRelations->aliasField($rightField), $name);
                });
            })
            ->where(function (QueryExpression $exp) use ($leftField, $rightField) {
                return $exp->or_(function (QueryExpression $exp) use ($leftField, $rightField) {
                    return $exp
                        ->isNotNull($this->LeftRelations->aliasField($leftField))
                        ->isNotNull($this->RightRelations->aliasField($rightField));
                });
            });
    }
}
