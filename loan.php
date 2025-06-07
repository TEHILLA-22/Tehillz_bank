<?php
// api/loans.php
if (basename($_SERVER['PHP_SELF']) == 'loans.php') {
    cors();
    
    $database = new Database();
    $db = $database->getConnection();
    $loanManager = new LoanManager($db);
    $userManager = new UserManager($db);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch($method) {
        case 'GET':
            if(!isset($_GET['wallet_address'])) {
                jsonResponse(['error' => 'Wallet address required'], 400);
            }
            
            $wallet_address = sanitizeInput($_GET['wallet_address']);
            $user = $userManager->getUserByWallet($wallet_address);
            
            if(!$user) {
                jsonResponse(['error' => 'User not found'], 404);
            }
            
            $loans = $loanManager->getUserLoans($user['id']);
            jsonResponse(['loans' => $loans]);
            break;
            
        case 'POST':
            if(!isset($input['wallet_address']) || !isset($input['amount'])) {
                jsonResponse(['error' => 'Missing required fields'], 400);
            }
            
            $wallet_address = sanitizeInput($input['wallet_address']);
            $user = $userManager->getUserByWallet($wallet_address);
            
            if(!$user) {
                jsonResponse(['error' => 'User not found'], 404);
            }
            
            $amount = floatval($input['amount']);
            $term = intval($input['term']);
            
            // Interest rates based on term
            $interest_rates = [30 => 5, 90 => 8, 180 => 12];
            $interest_rate = $interest_rates[$term] ?? 10;
            
            $total_repayment = $amount * (1 + $interest_rate / 100);
            
            $loanData = [
                'user_id' => $user['id'],
                'amount' => $amount,
                'rate' => $interest_rate,
                'term' => $term,
                'total' => $total_repayment,
                'status' => 'approved'
            ];
            
            $loan_id = $loanManager->createLoan($loanData);
            
            if($loan_id) {
                // Update user balance
                $new_balance = $user['bank_balance'] + $amount;
                $userManager->updateBalance($wallet_address, $new_balance);
                
                jsonResponse([
                    'message' => 'Loan approved successfully',
                    'loan_id' => $loan_id,
                    'amount' => $amount,
                    'total_repayment' => $total_repayment,
                    'due_days' => $term
                ]);
            } else {
                jsonResponse(['error' => 'Failed to process loan'], 500);
            }
            break;
            
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
}

?>