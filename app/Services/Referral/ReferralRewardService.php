<?php

namespace App\Services\Referral;

use App\Models\Admin\SystemSettingModel;
use App\Models\Orders;
use App\Models\ProviderProfile;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ReferralRewardService
{
    /**
     * Triggered whenever an order is marked as 'completed'.
     * Checks if this is the referred provider's FIRST completed order,
     * and if so, credits the referral_amount to the referrer's account out of Azhl's pocket.
     */
    public static function checkAndRewardReferrer(Orders $order)
    {
        try {
            $providerId = $order->provider_id;
            if (!$providerId) {
                return;
            }

            // 1. Check if this is the provider's FIRST completed order
            $completedOrdersCount = Orders::where('provider_id', $providerId)
                ->where('status', 'completed')
                ->count();

            // Must be the first completed order (count == 1)
            if ($completedOrdersCount > 1) {
                return;
            }

            // 2. Check if referral reward has already been issued for this referred provider
            $alreadyRewarded = ReferralReward::where('referred_user_id', $providerId)->exists();
            if ($alreadyRewarded) {
                return;
            }

            // 3. Find if this provider was referred by someone
            $providerProfile = ProviderProfile::where('user_id', $providerId)->first();
            $referrerId = optional($providerProfile)->referred_by_id;

            if (!$referrerId && $providerProfile && !empty($providerProfile->referred_by_code)) {
                $referrerProfile = ProviderProfile::where('referral_code', trim($providerProfile->referred_by_code))->first();
                $referrerId = optional($referrerProfile)->user_id;
            }

            if (!$referrerId) {
                $user = User::find($providerId);
                $referrerId = optional($user)->referred_by_id ?? optional($user)->referrer_id;
            }

            if (!$referrerId || (int) $referrerId === (int) $providerId) {
                return; // No valid referrer
            }

            // 4. Fetch Referral Amount set in System Settings
            $settings = SystemSettingModel::first();
            $referralAmount = (float) ($settings->referral_amount ?? 10.00);

            if ($referralAmount <= 0) {
                return;
            }

            // 5. Create Referral Reward Record (Paid by Azhl out of its pocket to Referrer)
            ReferralReward::create([
                'referrer_id' => $referrerId,
                'referred_user_id' => $providerId,
                'order_id' => $order->id,
                'reward_amount' => $referralAmount,
                'status' => 'credited',
            ]);

            Log::info("ReferralRewardService: Credited {$referralAmount} SAR referral bonus to Referrer #{$referrerId} for Provider #{$providerId}'s first completed order #{$order->id}");

        } catch (\Throwable $e) {
            Log::error('ReferralRewardService Error: ' . $e->getMessage());
        }
    }
}
