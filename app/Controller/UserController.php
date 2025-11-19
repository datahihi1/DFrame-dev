<?php

namespace App\Controller;

use App\Model\Users;
use DFrame\Application\DB;
use DFrame\Application\Validator;

class UserController extends Controller
{
    private Users $users;

    public function __construct(Users $users)
    {
        $this->users = $users;
    }
    public function listUsers()
    {
        $allUsers = $this->users
                         ->fetchAll();
        return $this->render('user1/list', ['users' => $allUsers]);
    }

    public function addUser()
    {
        return $this->render('user1/add');
    }

    public function storeUser(Validator $validator)
    {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';

        $validator->make(
            [
                'name' => $name,
                'email' => $email
            ],
            [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255'
            ],
            [
                'name.required' => 'Name is required.',
                'name.string' => 'Name must be a string.',
                'name.max' => 'Name must not exceed 255 characters.',
                'email.required' => 'Email is required.',
                'email.email' => 'Email must be a valid email address.',
                'email.max' => 'Email must not exceed 255 characters.'
            ]
        );

        if ($validator->fails()) {
            return $this->render('user1/add', ['error' => $validator->errors()]);
        }

        $this->users
             ->insert([
             'name' => $name,
             'email' => $email
             ])
             ->execute();
        header('Location: ' . route('user.list'));
        exit;
    }

    public function editUser($id)
    {
        return $this->render('user1/edit', ['user' => $this->users->where('id', $id)->first()]);
    }

    public function updateUser(Validator $validator, $id)
    {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';

        $validator->make(
            [
                'name' => $name,
                'email' => $email
            ],
            [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255'
            ],
            [
                'name.required' => 'Name is required.',
                'name.string' => 'Name must be a string.',
                'name.max' => 'Name must not exceed 255 characters.',
                'email.required' => 'Email is required.',
                'email.email' => 'Email must be a valid email address.',
                'email.max' => 'Email must not exceed 255 characters.'
            ]
        );

        if ($validator->fails()) {
            return $this->render('user1/edit', ['error' => $validator->errors()]);
        }

        DB::table('users')
            ->where('id', $id)
            ->update([
                'name' => $name,
                'email' => $email
            ])
            ->execute();
        header('Location: ' . route('user.list'));
        exit;
    }
    public function deleteUser($id)
    {
        $this->users
             ->delete($id)
             ->execute();
        header('Location: ' . route('user.list'));
        exit;
    }
}
