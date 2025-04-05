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
        $this->setRolesAndPermissions($role);

        $roleKey = Config::get('roles.key');

        if (!($this->data[$roleKey] ?? null)) {
            $this->db->query('ALTER TABLE ' . Config::get('db.table') . " ADD COLUMN $roleKey TEXT NOT NULL")->execute();
        }

        try {
            $this->db
                ->update(Config::get('db.table'))
                ->params([
                    $roleKey => json_encode($this->roles)
                ])
                ->where(Config::get('id.key'), $this->data[Config::get('id.key')])
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

        $this->permissions = $this->getRolePermissions($this->roles);

        $this->db
            ->update(Config::get('db.table'))
            ->params([
                Config::get('roles.key') => json_encode($this->roles)
            ])
            ->where(Config::get('id.key'), $this->data[Config::get('id.key')])
            ->execute();
    }

    /**
     * Set the roles and permissions for a user
     *
     * @param string|array $roles The role(s) to set
     */
    protected function setRolesAndPermissions($roles): void
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        foreach ($roles as $role) {
            if (!array_key_exists($role, Config::get('roles'))) {
                continue;
            }

            if (in_array($role, $this->roles)) {
                continue;
            }

            $this->roles[] = $role;
        }

        $this->permissions = $this->getRolePermissions($this->roles);
    }

    /**
     * Get the permissions for a role
     *
     * @param string|array $role
     * @return array
     */
    protected function getRolePermissions($roles): array
    {
        $allRoles = Config::get('roles');

        if (is_string($roles)) {
            return $allRoles[$roles] ?? [];
        }

        return array_values(array_unique(array_reduce($roles, function ($carry, $role) use ($allRoles) {
            if (isset($allRoles[$role])) {
                $carry = array_merge($carry, $allRoles[$role]); // Merge permissions for the selected role
            }

            return $carry;
        }, [])));
    }
}
