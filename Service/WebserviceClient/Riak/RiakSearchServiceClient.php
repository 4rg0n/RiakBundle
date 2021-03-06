<?php

namespace Kbrw\RiakBundle\Service\WebserviceClient\Riak;

use Guzzle\Http\Exception\CurlException;
use Kbrw\RiakBundle\Exception\RiakUnavailableException;
use Kbrw\RiakBundle\Model\Search\Query;
use Kbrw\RiakBundle\Model\Search\Response;
use Kbrw\RiakBundle\Service\WebserviceClient\BaseServiceClient;

class RiakSearchServiceClient extends BaseServiceClient
{
    /**
     * @param  \Kbrw\RiakBundle\Model\Cluster\Cluster       $cluster
     * @param  \Kbrw\RiakBundle\Model\Bucket\Bucket         $bucket
     * @param  \Kbrw\RiakBundle\Model\Search\Query | string $query
     * @return \Kbrw\RiakBundle\Model\Search\Response
     */
    public function search($cluster, $bucket, $query)
    {
        if (is_string($query)) $query = new Query($query);
        try {
            $request = $this->getClient($cluster->getGuzzleClientProviderService(), $this->getConfig($cluster, $bucket, $query))->get();
            $extra = array("method" => "GET");
            $response = $request->send();
            if ($response->getStatusCode() === 200) {
                $ts = microtime(true);
                $searchResponse = $this->getSerializer()->deserialize($response->getBody(), 'Kbrw\RiakBundle\Model\Search\Response', $query->getWt());
                $extra["deserialization_time"] = microtime(true) - $ts;
                if (isset($searchResponse)) {
                    $lists = $searchResponse->getLsts();
                    $list = reset($lists);
                    $qtime = $list->getSimpleTypeByName("QTime");
                    $extra["search_time"] = isset($qtime) ? ($qtime->getValue() / 1000) : null;
                }
                $this->logResponse($response, $extra);
                return $searchResponse;
            }
            $this->logResponse($response, $extra);
        } catch (CurlException $e) {
            $this->logger->err("Riak is unavailable" . $e->getMessage());
            throw new RiakUnavailableException();
        } catch (\Exception $e) {
            $this->logger->err("Unable to execute a search query. Full message is : \n" . $e->getMessage() . "");
        }

        return new Response();
    }

     /**
     * @param  \Kbrw\RiakBundle\Model\Cluster\Cluster $cluster
     * @param  \Kbrw\RiakBundle\Model\Bucket\Bucket   $bucket
     * @param  \Kbrw\RiakBundle\Model\Search\Query    $query
     * @return array<string,string>
     */
    public function getConfig($cluster, $bucket, $query)
    {
        $config = $query->getConfig();
        $config["protocol"] = $cluster->getProtocol();
        $config["domain"]   = $cluster->getDomain();
        $config["port"]     = $cluster->getPort();
        $config["bucket"]   = $bucket->getName();

        return $config;
    }

    public function getSerializer()
    {
        return $this->serializer;
    }

    public function setSerializer($serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @var \JMS\Serializer\Serializer
     */
    public $serializer;
}
