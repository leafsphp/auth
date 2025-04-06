<?php

namespace Leaf\Auth;

/**
 * Functionality for user subscriptions
 * ----
 * Addition to user class
 *
 * @version 0.1.0
 * @since 4.1.0
 */
trait UsesSubscriptions
{
    /**
     * User Subscription
     * @var array|null
     */
    protected $subscription = null;

    /**
     * Get current subscription
     *
     * @param string|array $subscription The subscription to assign
     * @return array|null
     */
    public function subscription(): ?array
    {
        if (billing()->tiers() === []) {
            return null;
        }

        if (
            !$this->subscription && (
                $this->subscription = db()
                    ->select('subscriptions')
                    ->where('user_id', $this->id())
                    ->first()
            )
        ) {
            $this->subscription['tier'] = billing()->tier($this->subscription['plan_id']);
        }

        return $this->subscription ? $this->subscription : null;
    }

    /**
     * Check if user has a subscription
     * @return bool
     */
    public function hasSubscription(): bool
    {
        return $this->subscription() && $this->subscription['status'] !== \Leaf\Billing\Subscription::STATUS_CANCELLED;
    }

    /**
     * Check if user has an active subscription
     * @return bool
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription() && ($this->subscription['status'] === \Leaf\Billing\Subscription::STATUS_ACTIVE || $this->subscription['status'] === \Leaf\Billing\Subscription::STATUS_TRIAL);
    }

    /**
     * Cancel current subscription
     * @return bool
     */
    public function cancelSubscription(): bool
    {
        $subscription = $this->subscription();

        if (!$subscription) {
            return true;
        }

        return billing()->cancelSubcription($subscription['subscription_id']);
    }
}
