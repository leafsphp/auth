<?php

namespace Leaf\Auth;

/**
 * Auth User Model
 * ----
 * Tiny model class for the user. Provides bare minimum
 * functionality for user and user data manipulation.
 *
 * @since 3.0.0
 */
class Model
{
    /**
     * User Information
     */
    protected User $user;

    /**
     * DB table name
     */
    protected string $table;

    /**
     * DB connection
     */
    protected \Leaf\Db $db;

    /**
     * User data to save
     */
    protected array $dataToSave = [];

    public function __construct($data)
    {
        $this->db = $data['db'];
        $this->user = $data['user'];
        $this->table = $data['table'];
    }

    /**
     * Create a new model resource
     *
     * @param array $data Data to be inserted
     *
     * @return bool
     */
    public function create(array $data): bool
    {
        $data['user_id'] = $this->user->id();

        if (Config::get('timestamps')) {
            $now = (new \Leaf\Date())->tick()->format(Config::get('timestamps.format'));
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        return $this->db->table($this->table)->insert($data)->execute();
    }

    /**
     * Update a model resource
     *
     * @param array $data Data to be updated
     *
     * @return \Leaf\Db
     */
    public function update(array $data): \Leaf\Db
    {
        $data['updated_at'] = (new \Leaf\Date())->tick()->format(Config::get('timestamps.format'));

        return $this->db->update($this->table)
            ->params($data)
            ->where('user_id', $this->user->id());
    }

    /**
     * Delete a model resource
     *
     * @return \Leaf\Db
     */
    public function delete(): \Leaf\Db
    {
        return $this->db->delete($this->table)
            ->where('user_id', $this->user->id());
    }

    /**
     * Get a model resource
     *
     * @param string $columns Columns to search by separated by commas or *
     *
     * @return \Leaf\Db
     */
    public function table($columns = '*'): \Leaf\Db
    {
        return $this->db->select($this->table, $columns)
            ->where('user_id', $this->user->id());
    }

    /**
     * Save a model resource
     *
     * @return bool
     */
    public function save(): bool
    {
        $success = $this->create($this->dataToSave);

        $this->dataToSave = [];

        return $success;
    }

    public function __set($name, $value)
    {
        $this->dataToSave[$name] = $value;
    }

    public function __call($name, $arguments)
    {
        return $this->get()->$name(...$arguments);
    }
}
