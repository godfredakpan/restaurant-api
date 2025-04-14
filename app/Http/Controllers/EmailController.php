<?php

namespace App\Http\Controllers;

use App\Mail\OrderRaveNotificationMail;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

use App\Models\PushMessages;


use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmailController extends Controller
{
    // Send Signup Email
    public function sendSignupEmail($userId)
    {
        $user = User::where('id', $userId)->first();
        if($user == null) return response()->json(['message' => 'User not found!'], 404);
        $subject = 'Welcome to Orderrave 🎉!';
        $messageBody = 'Thank you for signing up! Click the button below to confirm your email address.';
        $actionUrl = route('verification.verify', ['token' => $user->email_verification_token]);
        $actionText = 'Confirm Email';

        Mail::to($user->email)->send(new OrderRaveNotificationMail($subject, $messageBody, $actionUrl, $actionText, $user));

        return response()->json(['message' => 'Signup email sent successfully!']);
    }

    // sendWelcomeEmail with url to sign in
    public function sendWelcomeEmail($userId)
    {
        $user = User::where('id', $userId)->first();
        if($user == null) return response()->json(['message' => 'User not found!'], 404);
        $subject = 'Your email has been verified 🎉!';
        $messageBody = 'You have successfully verified your email address. Click the button below to sign in to your account.';
        $actionUrl = "https://app.orderrave.ng/auth/login";
        $actionText = 'Sign In';

        Mail::to($user->email)->send(new OrderRaveNotificationMail($subject, $messageBody, $actionUrl, $actionText, $user));

        return response()->json(['message' => 'Welcome email sent successfully!']);
    }

    // Send Sign-in Email
    public function sendSigninEmail($userId)
    {
        $user = User::where('id', $userId)->first();
        if($user == null) return response()->json(['message' => 'User not found!'], 404);
        $subject = 'New Sign-in Alert';
        $messageBody = 'A new sign-in to your account was detected. If this was not you, please secure your account.';
        $actionUrl = route('account.security', ['user' => $user->id]);
        $actionText = 'Review Account';

        Mail::to($user->email)->send(new OrderRaveNotificationMail($subject, $messageBody, $actionUrl, $actionText, $user));

        return response()->json(['message' => 'Sign-in email sent successfully!']);
    }

    // Send Transaction Notification
    public function sendTransactionEmail(Request $request, $userId)
    {
        $user = User::where('id', $userId)->first();
        if($user == null) return response()->json(['message' => 'User not found!'], 404);
        $subject = 'Transaction Notification';
        $messageBody = 'Your recent transaction has been completed successfully.';
        $actionUrl = null;
        $actionText = 'View Transaction on your app';
        
        $pin = is_array($request->pin) ? implode(',', $request->pin) : $request->pin;

        $transactionDetails = [
            'id' => $request->id,
            'amount' => $request->amount,
            'name' => $request->name,
            'recharge_code' => $request->recharge_code ?? null,
            'pin' => $pin ?? null
        ];

        Mail::to($user->email)->send(new OrderRaveNotificationMail($subject, $messageBody, $actionUrl, $actionText, $user, $transactionDetails));

        return response()->json(['message' => 'Transaction email sent successfully!']);
    }

    public function sendTransactionEmailFromTransaction($transaction, $userId)
    {
        $user = User::where('id', $userId)->first();
        if($user == null) return response()->json(['message' => 'User not found!'], 404);
        $subject = 'Transaction Notification';
        $messageBody = 'Your recent transaction has been completed successfully.';
        $actionUrl = null;
        $actionText = 'View Transaction on your app';
        
        $transactionDetails = [
            'id' => $transaction->id,
            'amount' => $transaction->amount,
            'name' => $transaction->name,
            'recharge_code' => $transaction->recharge_code ?? null,
            'pin' => $transaction->pin ?? null
        ];

        Mail::to($user->email)->send(new OrderRaveNotificationMail($subject, $messageBody, $actionUrl, $actionText, $user, $transactionDetails));

        return response()->json(['message' => 'Transaction email sent successfully!']);
    }

    // Send Email Verification Email
    public function sendVerificationEmail($userId)
    {
        $user = User::where('id', $userId)->first();
        if($user == null) return response()->json(['message' => 'User not found!'], 404);
        $token = Str::random(60);
        $user->email_verification_token = $token;
        $user->save();

        
        $subject = 'Email Verification';
        $messageBody = 'Please verify your email address by clicking the button below.';
        $actionUrl = route('verification.verify', ['token' => $user->email_verification_token]); // Assuming the token is already set in the user model
        $actionText = 'Verify Email';

        Mail::to($user->email)->send(new OrderRaveNotificationMail($subject, $messageBody, $actionUrl, $actionText, $user));

        return response()->json(['message' => 'Verification email sent successfully!']);
    }


    public function sendPasswordResetEmail($email)
    {
        $user = User::where('email', $email)->first();
        if($user == null) return response()->json(['message' => 'User not found!'], 404);
        $token = Str::random(60);
        $user->password_reset_token = $token;
        $user->save();

        
        $subject = 'Password Reset';
        $messageBody = 'Please reset your password by clicking the button below.';
        $actionUrl = route('password.reset', ['token' => $user->password_reset_token]); // Assuming the token is already set in the user model
        $actionText = 'Reset Password';

        Mail::to($user->email)->send(new OrderRaveNotificationMail($subject, $messageBody, $actionUrl, $actionText, $user));

        return response()->json(['message' => 'Password reset email sent successfully!']);
    }

    // new bank account generated
    public function sendBankAccountEmail($userId)
    {
        $user = User::where('id', $userId)->first();
        if($user == null) return response()->json(['message' => 'User not found!'], 404);
        $subject = 'Bank Account Generated';
        $messageBody = 'Your Naira bank account has been generated successfully; \n Bank name: '. $user->bank_name . ' Account name: '. $user->account_name . ' Account number: '. $user->account_number;
        $actionUrl = null;
        $actionText = null;

        Mail::to($user->email)->send(new OrderRaveNotificationMail($subject, $messageBody, $actionUrl, $actionText, $user));

        return response()->json(['message' => 'Bank account email sent successfully!']);
    }

    public function sendAccountDeletionEmail($userId, $link)
    {
        $user = User::where('id', $userId)->first();
        if ($user == null) return response()->json(['message' => 'User not found!'], 404);
        
        $subject = 'Account Deletion Request Received';
        $messageBody = 'We have received your request to delete your account. Your account and all associated data will be permanently deleted. If you did not request this deletion, please contact our support team immediately.';
        $actionUrl = $link;
        $actionText = 'Delete Account';

        Mail::to($user->email)->send(new OrderRaveNotificationMail($subject, $messageBody, $actionUrl, $actionText, $user));

        return response()->json(['message' => 'Account deletion notification email sent successfully!']);
    }

    // sendRefundEmail
    public function sendRefundEmail($transaction, $userId)
    {
        $user = User::where('id', $userId)->first();
        if($user == null) return response()->json(['message' => 'User not found!'], 404);
        $subject = 'Refund Notification';
        $messageBody = 'Your recent transaction has been refunded successfully.';
        $actionUrl = null;
        $actionText = 'View Transaction on your app';

        $transactionDetails = [
            'id' => $transaction->id,
            'amount' => $transaction->amount,
            'name' => $transaction->name,
            'recharge_code' => !empty($transaction->recharge_code) ? $transaction->recharge_code : null,
            'pin' => !empty($transaction->pin) ? $transaction->pin : null,
        ];

        Mail::to($user->email)->send(new OrderRaveNotificationMail($subject, $messageBody, $actionUrl, $actionText, $user, $transactionDetails));

        return response()->json(['message' => 'Refund email sent successfully!']);
    }


    public function sendOrderRaveEmail($email)
    {
        $subject = 'Welcome to OrderRave 🎉!';
        $messageBody = "Thank you for subscribing to OrderRave! " . "\n\n" . " We’re thrilled to have you on board." . "\n\n" . " Stay tuned for exciting updates and offers!";

        $optionalSlackMessage = "Please feel free to share feedback, suggestions, or questions with us. " . "\n\n" . " We'd love to hear from you! " . "\n\n" . " Thank you," . "\n" . " Order Rave Team";

        $actionUrl = null;
        $actionText = null;

        $messageBodyWithSlack = nl2br($messageBody . "\n\n" . $optionalSlackMessage);

        // Send email
        Mail::to($email)->send(new OrderRaveNotificationMail($subject, $messageBodyWithSlack, $actionUrl, $actionText));
        Mail::to("godfredakpan@gmail.com")->send(new OrderRaveNotificationMail("Someone just subscribed", "New subscriber: " . $email . "\n", null, null));

        // Response
        return response()->json(['message' => 'Subscription email sent successfully!']);
    }

    public function sendOrderRaveEmailUpdate(Request $request)
    {
        $emails = $request->input('emails'); 
        $subject = $request->input('subject'); 
        $messageBody = $request->input('body'); 
        $link = $request->input('link') ?? null;

        if (!empty($link)) {
            $actionUrl = $link;
            $actionText = 'View';
        }

        if (empty($emails) || !is_array($emails)) {
            return response()->json(['message' => 'No valid email addresses provided.'], 400);
        }

        if (empty($subject) || empty($messageBody)) {
            return response()->json(['message' => 'Email subject or body is missing.'], 400);
        }

        try {
            foreach ($emails as $email) {
                Mail::to($email)->send(new OrderRaveNotificationMail($subject, nl2br($messageBody), $actionUrl, $actionText));
            }

            return response()->json(['message' => 'Emails sent successfully!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send emails.', 'error' => $e->getMessage()], 500);
        }
    }

    public function sendQROrderNotification($email, $order)
    {
        $subject = 'Your QR Code Order Confirmation!';
        $messageBody = "Dear {$order->name},\n\nThank you for your order of '{$order->design}' priced at ₦{$order->price} for one. \n\nWe will get in touch with you shortly.\n\nRegards,\nOrder Rave Team";

        // Send email to user
        Mail::to($email)->send(new OrderRaveNotificationMail($subject, nl2br($messageBody), null, null));

        // Notify admin
        $adminMessage = "New QR code order placed:\n\nName: {$order->name}\nEmail: {$order->email}\nPhone: {$order->phone}\nAddress: {$order->address}\nDesign: {$order->design}\nPrice: ₦{$order->price} for one\nMessage: {$order->message}";
        Mail::to("godfredakpan@gmail.com")->send(new OrderRaveNotificationMail("New QR Code Order", nl2br($adminMessage), null, null));

        return response()->json(['message' => 'Order notification emails sent successfully!']);
    }

    public function sendOrderNotification($email, $order, $shopEmail)
    {
        // Prepare item details for the email
        $itemDetails = "Order Items:\n";
        foreach ($order->items as $item) {
            $menuItem = \App\Models\MenuItem::find($item->menu_item_id);
            $itemDetails .= "- {$menuItem->name} (Quantity: {$item->quantity}, Price: ₦{$menuItem->price})\n";
        }

        // Email to the user (if email is provided)
        if ($email) {
            $subject = 'Your Order Confirmation!';
            $messageBody = "Dear {$order->user_name},\n\nThank you for your order. Below are the details of your order:\n\n"
                . "Order Number: {$order->order_number}\n"
                . "Order Type: {$order->order_type}\n"
                . "Order Total: ₦{$order->order_total}\n";

            // Add optional fields if they exist
            if (!empty($order->additional_notes)) {
                $messageBody .= "Additional Notes: {$order->additional_notes}\n";
            }
            if (!empty($order->address)) {
                $messageBody .= "Address: {$order->address}\n";
            }
            if (!empty($order->user_phone)) {
                $messageBody .= "Phone: {$order->user_phone}\n";
            }

            $messageBody .= "\n{$itemDetails}\n\n"
                . "We will get in touch with you shortly.\n\nRegards,\nYour Shop Team";

            // Send email to user
            Mail::to($email)->send(new OrderRaveNotificationMail($subject, nl2br($messageBody), null, null));
        }

        // Email to the admin (shop email)
        $adminSubject = 'New Order Placed';
        $adminMessage = "A new order has been placed:\n\n"
            . "Order Number: {$order->order_number}\n"
            . "Customer Name: {$order->user_name}\n";

        // Add optional fields if they exist
        if (!empty($email)) {
            $adminMessage .= "Customer Email: {$email}\n";
        }
        if (!empty($order->user_phone)) {
            $adminMessage .= "Customer Phone: {$order->user_phone}\n";
        }

        $adminMessage .= "Order Type: {$order->order_type}\n"
            . "Order Total: ₦{$order->order_total}\n";

        if (!empty($order->additional_notes)) {
            $adminMessage .= "Additional Notes: {$order->additional_notes}\n";
        }
        if (!empty($order->address)) {
            $adminMessage .= "Address: {$order->address}\n";
        }
        if (!empty($order->table_number)) {
            $adminMessage .= "Table Number: {$order->table_number}\n";
        }
        if (!empty($order->hotel_room)) {
            $adminMessage .= "Hotel Room: {$order->hotel_room}\n";
        }
        if (!empty($order->payment_status)) {
            $adminMessage .= "Payment Status: {$order->payment_status}\n";
        }

        $adminMessage .= "\n{$itemDetails}\n\n"
            . "Please process the order as soon as possible.";

        $actionUrl = "https://app.orderrave.ng/orders/manage";
        $actionText = 'View Order';

        // Send email to admin (shop email)
        Mail::to($shopEmail)->send(new OrderRaveNotificationMail($adminSubject, nl2br($adminMessage), $actionUrl, $actionText));

        return response()->json(['message' => 'Order notification emails sent successfully!']);
    }

}
