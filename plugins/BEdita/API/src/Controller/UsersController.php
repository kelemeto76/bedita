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
namespace BEdita\API\Controller;

use Cake\ORM\TableRegistry;

/**
 * Controller for /users endpoint
 *
 * @property \BEdita\Core\Model\Table\UsersTable $Users
 */
class UsersController extends AppController
{
    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
        parent::initialize();
        $this->Users = TableRegistry::get('Users');
    }

    /**
     * Paginated users index
     *
     * @return void
     */
    public function index()
    {
        $users = $this->Users->find('all');
        $this->prepareResponseData($users, true, 'users');
    }

    /**
     * Get user's data.
     *
     * @param int $id User ID.
     * @return void
     */
    public function view($id)
    {
        $user = $this->Users->get($id);

        $this->prepareResponseData($user, false, 'users');
    }
}