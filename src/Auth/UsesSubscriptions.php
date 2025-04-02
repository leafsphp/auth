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
    public function subscription()
    {
        if (!$this->subscription) {
            $this->subscription = db()
                ->select('subscriptions')
                ->where('user_id', $this->id())
                ->first();

            $this->subscription['tier'] = billing()->tier($this->subscription['plan_id']);
        }

        return $this->subscription;
    }

}
