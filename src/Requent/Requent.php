<?php

/**
 * This is the main class to start building the query
 * and calling the appropriate query builder method to
 * get result and running it through the transformers.
 */

namespace Requent;

use BadMethodCallException;
use Requent\Traits\QueryBuilderMethods;
use Illuminate\Database\Eloquent\Builder;

class Requent
{
	use QueryBuilderMethods;

	/**
	 * The resource Model/QueryBuilder instance
	 * @var Mixed
	 */
	protected $model = null;

	/**
	 * The final builder instance after building the query
	 * @var Illuminate\Database\Eloquent\Builder
	 */
	protected $builder = null;

	/**
	 * The request query string parameters
	 * @var Array
	 */
	protected $queryString = null;

	/**
	 * The transformer to transform the result
	 * @var Mixed
	 */
	protected $transformer = null;

	/**
	 * The key to wrap the collection
	 * @var String
	 */
	protected $collectionKey = null;

	/**
	 * The selected columns
	 * @var array
	 */
	protected $selectedColumns = [];

	/**
	 * Don't transform the query result
	 * @var boolean
	 */
	protected $original = false;

	/**
	 * Construct the Requent Object
	 * @param Array|null $queryString
	 * @return void
	 */
	public function __construct(Array $config = [], Array $queryString = [], Array $selectedColumns)
	{
		$this->config = $config;
		$this->queryString = $queryString;
		$this->selectedColumns = $selectedColumns;
	}

	/**
	 * Set the resource and transformer for the query
	 * @param  Mixed $model Model Name|Object
	 * @param  Mixed $transformer Transform Name|Object
	 * @return $this
	 */
	public function resource($model, $transformer = null)
    {
    	return $this->init($model, $transformer)->buildQuery();
    }

/**
 * Return the original data without transforming
 * @return $this
 */
    public function original()
    {
    	$this->original = true;
    	return $this;
    }

    /**
     * Set the transformer
     * @param  Mixed $transformer
     * @return $this
     */
	public function transformBy($transformer)
    {
    	$this->original = false;
    	$this->transformer = $transformer;
    	return $this;
    }

    /**
     * Set the result wrapper key for collection
     * @param  String $key
     * @return $this
     */
    public function keyBy($key)
    {
    	$this->collectionKey = $key;
    	return $this;
    }

    /**
     * Bootstrapping the Requent
     * @param  Mixed $model String|Object
     * @param  Mixed $transformer String|Object
     * @return $this
     */
    protected function init($model, $transformer)
	{
		$this->transformer = $transformer;
		$this->collectionKey = $this->getConfigValue('collection_key');
		if(!is_null($model)) {
			$this->model = is_string($model) ? new $model : $model;
		}
		return $this;
	}

	/**
	 * Build the query from request
	 * @return $this
	 */
	protected function buildQuery()
	{
		$this->builder = $this->filter(
			$this->applySearch($this->getBuilder()),
			$this->getQueryableData()
		);
		return $this;
	}

	/**
	 * Get the query parameter data (key/value pair)
	 * @return Array
	 */
	protected function getQueryableData()
	{
		$key = $this->getConfigValue('query_parameter_name');
		return [$key => $this->selectedColumns];
	}

	/**
	 * Get the resource for building the query
	 * @return Illuminate\Database\Eloquent\Builder
	 */
    protected function getBuilder()
    {
    	if ($this->model instanceof Builder) {
    		return $this->model;
    	}
    	return $this->model->query();
    }

    /**
     * Filter the request and build query
     * @param  QueryBuilder $query
     * @param  Mixed $values
     * @return Illuminate\Database\Eloquent\Builder
     */
	protected function filter($query, $values)
	{
		foreach ($values as $method => $value) {
			if($this->methodExists($method)) {
				call_user_func_array([$this, $method], [$query, $value]);
			}
		}
		return $query;
	}

	/**
	 * Check available methods that are callable to build the query
	 * @param  String $method
	 * @return Mixed
	 */
	protected function methodExists($method)
	{
		if(!$this->isQueryBuilderMethod($method)) {
			return method_exists($this, $method) || $this->isFieldParser($method);
		}
		return false;
	}

	/**
	 * Check if the method belongs to QueryBuilder
	 * @param  String $method
	 * @return Boolean
	 */
	protected function isQueryBuilderMethod($method)
	{
		return in_array($method, get_class_methods(QueryBuilderMethods::class));
	}

	/**
	 * Check if the field parser method is being called
	 * @param  String $method
	 * @return Boolean
	 */
	protected function isFieldParser($method)
	{
		return $method == $this->getConfigValue('query_parameter_name');
	}

	/**
	 * Add query constraints for relation
	 * @param Illuminate\Database\Eloquent\Builder
	 * @param Array
	 * @return Illuminate\Database\Eloquent\Builder
	 */
	protected function addFields($query, $fields)
	{
		foreach((Array) $fields as $field => $value) {
			$this->addField($query, $field, $value);
		}
		return $query;
	}

	/**
	 * Add query constraint for relation
	 * @param Illuminate\Database\Eloquent\Builder
	 * @param String Field Name
	 * @param Mixed
	 * @return Illuminate\Database\Eloquent\Builder
	 */
	protected function addField($query, $field, $value)
	{
		if($method = $this->getRelationShipName($query, $field)) {
			if($value === true) {
				$query->with($method);
			} else {
				$query->with([$method => function($query) use ($value) {
					$this->filter($query, $value);
				}]);
			}
		}
	}

	/**
	 * Get the relationship method name
	 * @param  Illuminate\Database\Eloquent\Builder
	 * @param  String
	 * @return Mixed
	 */
	protected function getRelationShipName($query, $method)
	{
		if(method_exists($query->getModel(), $method)) {
			return $method;
		}
	}

	/**
	 * Get the query string value for search
	 * @return String
	 */
	protected function getSearchQueryValue()
	{
		return $this->getQueryStringValue(
			$this->getConfigValue('search_parameter_name')
		);
	}

	/**
     * Get query string parameter
     * @param  String $key
     * @return Mixed
     */
    protected function getQueryStringValue($key = null)
    {
    	if(!$key) return $this->queryString;

    	if(isset($this->queryString[$key])) {
    		return $this->queryString[$key];
    	}
    }

    /**
     * Get config item
     * @param  String $key
     * @return Mixed
     */
    protected function getConfigValue($key = null)
    {
    	if(!$key) return $this->config;

    	if(isset($this->config[$key])) {
    		return $this->config[$key];
    	}
    }

	/**
	 * Start to apply search using LIKE operator on searchable fields
	 * @param  Illuminate\Database\Eloquent\Builder $builder
	 * @return Illuminate\Database\Eloquent\Builder
	 * @throws BadMethodCallException
	 */
	protected function applySearch(Builder $builder)
    {
        try {
        	if ($search = $this->getSearchQueryValue()) {
	            $searchables = (Array) $builder->getModel()->requentSearchables();
	            $builder->where(function($query) use ($searchables, $search) {
	            	$search = strpos($search, '%') !== false ? $search : "%$search%";
	                $query->where(array_shift($searchables), 'LIKE', $search);
	                $this->buildSearchQuery($query, $searchables, $search);
	            });
	        }
	        return $builder;
        } catch(BadMethodCallException $e) {
        	return $builder;
        }
    }

    /**
     * Loop and apply search using LIKE operator on all searchable fields
     * @param  Illuminate\Database\Eloquent\Builder $builder 
     * @param  Array  $searchables
     * @param  String  $search
     * @return Illuminate\Database\Eloquent\Builder
     */
    protected function buildSearchQuery(Builder $builder, $searchables, $search)
    {
        foreach($searchables as $key => $searchable) {
            if(is_array($searchable) && method_exists($builder->getModel(), $key)) {
                $builder->orWhereHas($key, function($query) use ($searchable, $search) {
                    $query->where(array_shift($searchable), 'LIKE', $search);
                    $this->buildSearchQuery($query, $searchable, $search);
                });
            } else {
                $builder->orWhere($searchable, 'LIKE', $search);
            }
        }

        return $builder;
    }

    /**
	 * Catch missing methods
	 * @param  String $method
	 * @param  Array $params
	 * @return Mixed
	 * @throws BadMethodCallException
	 */
	public function __call($method, $params)
	{
		if($this->isFieldParser($method)) {
			return $this->addFields(...$params);
		} elseif ($method == 'model') {
			return $this->resource(...$params);
		}

		throw new BadMethodCallException("Call to undefined method {$method}");
	}
}
