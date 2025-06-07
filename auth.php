<?php
include 'config.php';
// api/auth.php
if (basename($_SERVER['PHP_SELF']) == 'auth.php') {
    cors();
    
    $database = new Database();
    $db = $database->getConnection();
    $userManager = new UserManager($db);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch($method) {
        case 'POST':
            if(!isset($input['wallet_address']) || !validateWalletAddress($input['wallet_address'])) {
                jsonResponse(['error' => 'Invalid wallet address'], 400);
            }
            
            $wallet_address = sanitizeInput($input['wallet_address']);
            $email = isset($input['email']) ? sanitizeInput($input['email']) : null;
            
            // Check if user exists
            $user = $userManager->getUserByWallet($wallet_address);
            
            if(!$user) {
                // Create new user
                $user_id = $userManager->createUser($wallet_address, $email);
                if($user_id) {
                    $user = $userManager->getUserByWallet($wallet_address);
                    jsonResponse([
                        'message' => 'User created successfully',
                        'user' => $user,
                        'token' => generateToken()
                    ]);
                } else {
                    jsonResponse(['error' => 'Failed to create user'], 500);
                }
            } else {
                jsonResponse([
                    'message' => 'User authenticated',
                    'user' => $user,
                    'token' => generateToken()
                ]);
            }
            break;
            
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
}

