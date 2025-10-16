<?php
namespace App\Services;

class AuthService {
    public static function getCurrentUser() {
        return [
            'id' => 1,
            'name' => 'Jean Dupont',
            'role' => 'Technicien IT',
            'avatar' => 'JD'
        ];
    }

    public static function isAuthenticated() {
        return true; // Pour le POC
    }
}
