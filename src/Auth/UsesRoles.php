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
     * Assign new role to user
     *
     * @param string|array $role The role to assign
     * @return bool
     */
    public function assign($role): bool
    {
        if (!array_key_exists($role, Config::get('roles'))) {
            return false;
        }

        if (in_array($role, $this->roles)) {
            return true;
        }

        $roleKey = Config::get('roles.key');

        $this->setRolesAndPermissions($role);

        if (!($this->data[$roleKey] ?? null)) {
            $this->db->query("ALTER TABLE users ADD COLUMN $roleKey TEXT NOT NULL DEFAULT '[]'")->execute();
        }

        try {
            $this->db
                ->update('users')
                ->params([
                    $roleKey => json_encode($this->roles)
                ])
                ->where(Config::get('id.key'), $this->data['id'])
                ->execute();
        } catch (\Throwable $th) {
            return false;
        }

        return true;
    }

    /**
     * Check if user has a permission
     * @param string|array $permission The permission(s) to check
     * @return bool
     */
    public function can($permission): bool
    {
        if (is_array($permission)) {
            return count(array_intersect($permission, $this->permissions)) > 0;
        }

        return in_array($permission, $this->permissions);
    }

    /**
     * Check if a user does not have a permission
     */
    public function cannot($permission): bool
    {
        return !$this->can($permission);
    }

    /**
     * Check if user has a role
     * @param string|array $role The role(s) to check
     * @return bool
     */
    public function is($role): bool
    {
        if (is_array($role)) {
            return count(array_intersect($role, $this->roles)) > 0;
        }

        return in_array($role, haystack: $this->roles);
    }

    /**
     * Check if user does not have a role
     */
    public function isNot($role): bool
    {
        return !$this->is($role);
    }

    /**
     * Return the user's roles
     * @return array
     */
    public function roles(): array
    {
        return $this->roles;
    }

    /**
     * Return the user's permissions
     * @return array
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /**
     * Remove a role from a user
     * @param string|array $role The role(s) to revoke
     */
    public function unassign($role): void
    {
        $this->roles = array_diff(
            $this->roles,
            is_array($role) ? $role : [$role]
        );

        $this->permissions = array_diff(
            $this->permissions,
            $this->getRolePermissions($role)
        );

        $this->db
            ->update('users')
            ->params([
                Config::get('roles.key') => json_encode($this->roles)
            ])
            ->where(Config::get('id.key'), $this->data['id'])
            ->execute();
    }

    /**
     * Set the roles and permissions for a user
     *
     * @param string|array $role The role(s) to set
     */
    protected function setRolesAndPermissions($role): void
    {
        $this->roles = array_merge(
            $this->roles,
            is_array($role) ? $role : [$role]
        );

        foreach ($this->roles as $role) {
            $this->permissions = array_merge($this->permissions, $this->getRolePermissions($role));
        }
    }

    /**
     * Get the permissions for a role
     *
     * @param string|array $role
     * @return array
     */
    protected function getRolePermissions($role): array
    {
        if (is_string($role)) {
            return Config::get('roles')[$role] ?? [];
        }

        return array_reduce($role, function ($acc, $role) {
            return array_merge($acc, Config::get('roles')[$role] ?? []);
        }, []);
    }
}
