<?php

namespace Leaf\Auth;

/**
 * Functionality for user permissions
 * ----
 * Addition to user class
 *
 * @version 0.1.0
 * @since 3.0.0
 */
trait UsesRoles
{
    /**
     * User Permissions
     */
    protected array $permissions = [];

    /**
     * User Roles
     */
    protected array $roles = [];

    /**
     * Grant a user permission to do something
     * @param string|array $permission The permission to grant
     */
    public function grantPermissions($permission): void
    {
        // will probably have to verify permissions here

        $this->permissions = array_merge(
            $this->permissions,
            is_array($permission) ? $permission : [$permission]
        );
    }

    /**
     * Assign new role to user
     * @param string|array $role The role to assign
     */
    public function assignRoles($role): void
    {
        // will need to verify roles here

        $this->roles = array_merge(
            $this->roles,
            is_array($role) ? $role : [$role]
        );

        // persist via storage contract

        foreach ($this->roles as $role) {
            $this->grantPermissions($this->getRolePermissions($role));
        }

        // persist via storage contract
    }

    /**
     * Check if user has a permission
     * @param string|array $permission The permission(s) to check
     * @return bool
     */
    public function can($permission): bool
    {
        if (is_array($permission)) {
            return count(array_intersect($permission, $this->permissions)) === count($permission);
        }

        return in_array($permission, $this->permissions);
    }

    /**
     * Check if user has a role
     * @param string|array $role The role(s) to check
     * @return bool
     */
    public function is($role): bool
    {
        if (is_array($role)) {
            return count(array_intersect($role, $this->roles)) === count($role);
        }

        return in_array($role, $this->roles);
    }

    /**
     * Revoke a permission from a user
     * @param string|array $permission The permission(s) to revoke
     */
    public function revokePermissions($permission): void
    {
        // persist via storage contract
        $this->permissions = array_diff(
            $this->permissions,
            is_array($permission) ? $permission : [$permission]
        );
    }

    /**
     * Remove a role from a user
     * @param string|array $role The role(s) to revoke
     */
    public function removeRoles($role): void
    {
        // persist via storage contract
        $this->roles = array_diff(
            $this->roles,
            is_array($role) ? $role : [$role]
        );
    }

    /**
     * Get the permissions for a role
     * @param string $role
     * @return array
     */
    protected function getRolePermissions($role): array
    {
        // get permissions from storage contract
        return [];
    }
}
