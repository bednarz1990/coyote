<?php

namespace Coyote;

use Coyote\Services\Elasticsearch\Analyzers\AnalyzerInterface;
use Coyote\Services\Elasticsearch\Transformers\CommonTransformer;
use Coyote\Services\Elasticsearch\Transformers\TransformerInterface;
use Illuminate\Contracts\Support\Arrayable;

trait Searchable
{
    /**
     * @var string
     */
    protected $transformer = CommonTransformer::class;

    /**
     * @var string
     */
    protected $analyzer;

    /**
     * Index data in elasticsearch
     *
     * @return mixed
     */
    public function putToIndex()
    {
        $params = $this->getParams();
        $params['body'] = $this->analyze($this->getIndexBody());

        return $this->getClient()->index($params);
    }

    /**
     * Delete document from index
     *
     * @return mixed
     */
    public function deleteFromIndex()
    {
        return $this->getClient()->delete($this->getParams());
    }

    /**
     * @param array $body
     * @return mixed
     */
    public function search($body)
    {
        $params = $this->getParams();
        $params['body'] = $body;

        return $this->getTransformer($this->getClient()->search($params));
    }

    /**
     * Put mapping to elasticsearch's type
     */
    public function putMapping()
    {
        $mapping = $this->getMapping();

        if (!empty($mapping)) {
            $params = $this->getParams();
            $params['body'] = $mapping;

            $this->getClient()->indices()->putMapping($params);
        }
    }

    /**
     * @param string $transformer
     */
    public function setTransformer(string $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * @param string $analyzer
     */
    public function setAnalyzer(string $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    /**
     * Default data to index in elasticsearch
     *
     * @return mixed
     */
    protected function getIndexBody()
    {
        $body = $this->toArray();

        foreach (['created_at', 'updated_at', 'deadline_at', 'last_post_created_at'] as $column) {
            if (!empty($body[$column])) {
                $body[$column] = date('Y-m-d H:i:s', strtotime($body[$column]));
            }
        }

        return $body;
    }

    /**
     * Get model's mapping
     *
     * @return array
     */
    protected function getMapping()
    {
        return [
            $this->getTable() => [
                'properties' => $this->mapping
            ]
        ];
    }

    /**
     * Basic elasticsearch params
     *
     * @return array
     */
    protected function getParams()
    {
        $params = [
            'index'     => $this->getIndexName(),
            'type'      => $this->getTable()
        ];

        if ($this->getKey()) {
            $params['id'] = $this->getKey();
        }

        return $params;
    }

    /**
     * Convert model to array
     *
     * @param mixed $data
     * @return array
     */
    protected function analyze($data)
    {
        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        }

        foreach ($data as &$value) {
            if (is_object($value) && $data instanceof Arrayable) {
                $value = $this->analyze($value);
            }
        }

        if ($this->analyzer) {
            $data = $this->getAnalyzer()->analyze($data);
        }

        return $data;
    }

    /**
     * Get client instance
     *
     * @return \Illuminate\Foundation\Application|mixed
     */
    protected function getClient()
    {
        return app('elasticsearch');
    }

    /**
     * @return AnalyzerInterface
     */
    protected function getAnalyzer(): AnalyzerInterface
    {
        return app($this->analyzer);
    }

    /**
     * @param array $response
     * @return TransformerInterface
     */
    protected function getTransformer(array $response): TransformerInterface
    {
        return app($this->transformer, [$response]);
    }

    /**
     * Get default index name from config
     *
     * @return mixed
     */
    protected function getIndexName()
    {
        return config('elasticsearch.default_index');
    }
}
