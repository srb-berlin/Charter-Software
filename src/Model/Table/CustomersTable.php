<?php
namespace App\Model\Table;

use App\Model\Entity\Customer;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Customers Model
 */
class CustomersTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        $this->table('customers');
        $this->displayField('id');
        $this->primaryKey('id');
        $this->belongsTo('CustomerTypes', [
            'foreignKey' => 'customer_type_id',
            'joinType' => 'INNER'
        ]);
        $this->hasMany('Flights', [
            'foreignKey' => 'customer_id'
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->add('id', 'valid', ['rule' => 'numeric'])
            ->allowEmpty('id', 'create');
            
        $validator
            ->requirePresence('first_name', 'create')
            ->notEmpty('first_name');
            
        $validator
            ->requirePresence('last_name', 'create')
            ->notEmpty('last_name');
            
        $validator
            ->requirePresence('company', 'create')
            ->notEmpty('company');
            
        $validator
            ->requirePresence('street', 'create')
            ->notEmpty('street');
            
        $validator
            ->add('postal_code', 'valid', ['rule' => 'numeric'])
            ->requirePresence('postal_code', 'create')
            ->notEmpty('postal_code');
            
        $validator
            ->requirePresence('country', 'create')
            ->notEmpty('country');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->existsIn(['customer_type_id'], 'CustomerTypes'));
        return $rules;
    }
}
