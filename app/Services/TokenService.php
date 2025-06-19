<?php
namespace App\Services;

use App\Models\Personal_Access_Token;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class TokenService
{
    public function createAndStoreToken($user)
    {
        $expiresAt = now()->addMinutes(1440*30);
        $shortTokenExpiryDatetime = now()->addMinutes(60*5);

        $plainTextToken = $user->createToken('token')->plainTextToken;
        $hashedPlainTextToken = Crypt::encryptString($plainTextToken);

        $tokenId = explode('|', $plainTextToken)[0];
        $tokenModel = Personal_Access_Token::find($tokenId);
        $tokenModel->expires_at = $expiresAt;
        $tokenModel->short_token_expiry = $shortTokenExpiryDatetime;
        $tokenModel->save();

        return $tokenModel;
    }
    public function createAndStoreWebToken($user)
    {
        $expiresAt = now()->addMinutes(1440);
        $shortTokenExpiryDatetime = now()->addMinutes(60);

        $plainTextToken = $user->createToken('token')->plainTextToken;
        $hashedPlainTextToken = Crypt::encryptString($plainTextToken);

        $tokenId = explode('|', $plainTextToken)[0];
        $tokenModel = Personal_Access_Token::find($tokenId);
        $tokenModel->expires_at = $expiresAt;
        $tokenModel->plainTextToken = $hashedPlainTextToken;
        $tokenModel->short_token_expiry = $shortTokenExpiryDatetime;
        $tokenModel->save();

        return $tokenModel;
    }
    public function validateToken($token)
    {
        $tokenModel = Personal_Access_Token::where('token', $token)->first();
        if (!$tokenModel) {
            return null;
        }

        if ($tokenModel->expires_at && Carbon::now()->greaterThan($tokenModel->expires_at)) {
            return null;
        }

        $user = $tokenModel->user;

        return $user;
    }
    public function validateWebToken($token)
    {
        $tokenModel = Personal_Access_Token::where('token', $token)->first();

        if (!$tokenModel) {
            return null;
        }

        if ($tokenModel->short_token_expiry && Carbon::now()->greaterThan($tokenModel->short_token_expiry)) {
            return null;
        }

        $user = $tokenModel->tokenable;

        return $user;
    }
    public function revokeToken($token)
    {
        $tokenModel = Personal_Access_Token::where('token', $token)->first();

        if ($tokenModel) {
            $tokenModel->delete();
        }
    }
    public function revokeWebToken($token)
    {
        $tokenModel = Personal_Access_Token::where('token', $token)->first();

        if ($tokenModel) {
            $tokenModel->delete();
        }
    }
    public function getToken($token)
    {
        $tokenModel = Personal_Access_Token::where('token', $token)->first();

        if ($tokenModel) {
            return $tokenModel;
        }

        return null;
    }
    public function getWebToken($token)
    {
        $tokenModel = Personal_Access_Token::where('token', $token)->first();

        if ($tokenModel) {
            return $tokenModel;
        }

        return null;
    }
    public function getTokenById($tokenId)
    {
        $tokenModel = Personal_Access_Token::find($tokenId);

        if ($tokenModel) {
            return $tokenModel;
        }

        return null;
    }
    public function getWebTokenById($tokenId)
    {
        $tokenModel = Personal_Access_Token::find($tokenId);

        if ($tokenModel) {
            return $tokenModel;
        }

        return null;
    }
    public function getTokenByUserId($userId)
    {
        $tokenModel = Personal_Access_Token::where('tokenable_id', $userId)->first();

        if ($tokenModel) {
            return $tokenModel;
        }

        return null;
    }
    public function getWebTokenByUserId($userId)
    {
        $tokenModel = Personal_Access_Token::where('tokenable_id', $userId)->first();

        if ($tokenModel) {
            return $tokenModel;
        }

        return null;
    }
}
