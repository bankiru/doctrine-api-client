[![Latest Stable Version](https://poser.pugx.org/bankiru/doctrine-api-client/v/stable)](https://packagist.org/packages/bankiru/doctrine-api-client) 
[![Total Downloads](https://poser.pugx.org/bankiru/doctrine-api-client/downloads)](https://packagist.org/packages/bankiru/doctrine-api-client) 
[![Latest Unstable Version](https://poser.pugx.org/bankiru/doctrine-api-client/v/unstable)](https://packagist.org/packages/bankiru/doctrine-api-client) 
[![License](https://poser.pugx.org/bankiru/doctrine-api-client/license)](https://packagist.org/packages/bankiru/doctrine-api-client)

[![Build Status](https://travis-ci.org/bankiru/doctrine-api-client.svg)](https://travis-ci.org/bankiru/doctrine-api-client?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/bankiru/doctrine-api-client/badges/quality-score.png)](https://scrutinizer-ci.com/g/bankiru/doctrine-api-client/)
[![Code Coverage](https://scrutinizer-ci.com/g/bankiru/doctrine-api-client/badges/coverage.png)](https://scrutinizer-ci.com/g/bankiru/doctrine-api-client/)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/2c1f5c41-86de-4441-9876-b0ee05d012af/mini.png)](https://insight.sensiolabs.com/projects/2c1f5c41-86de-4441-9876-b0ee05d012af)

# Doctrine-faced RPC Client

Implementation of `doctrine\common` interfaces with RPC interfaces (`scaytrase\rpc-common`)

## Usage

```php
class \MyVendor\Api\Entity\MyEntity {
    /** @var string */
    private $id;
    /** @var string */
    private $payload;
    
    public function getId() { return $this->id; }
    
    public function getPayload() { return $this->payload; }
}
  
```  
    
`Resources/config/api/MyEntity.api.yml` content:
    
```yml
MyVendor\Api\Entity\MyEntity:
  type: entity
  id:
    id:
      type: int

  fields:
    payload:
      type: string
    
  client:
    name: my-client
    entityPath: my-entity
```

Configure `EntityManager`
```php
class RpcClient implements RpcClientInterface {
    /** RpcClient impl */
}

$client = new RpcClient();

$registry = new ClientRegistry();
$registry->add('my-client', $client);

$configuration = new Configuration();
$configuration->setMetadataFactory(new EntityMetadataFactory());
$configuration->setRegistry($this->registry);
$configuration->setProxyDir(CACHE_DIR . '/doctrine/proxy/');
$configuration->setProxyNamespace('MyVendor\Api\Proxy');
$driver = new MappingDriverChain();
$driver->addDriver(
    new YmlMetadataDriver(
        new SymfonyFileLocator(
            [
                __DIR__ . '/../Resources/config/api/' => 'MyVendor\Api\Entity',
            ],
            '.api.yml',
            DIRECTORY_SEPARATOR)
    ),
    'MyVendor\Api\Entity'
);
$configuration->setDriver($driver);

$manager = new EntityManager($configuration);    
```  
    

Call entity-manager to retrieve entities through your api
```php
$samples = $manager->getRepository(\MyVendor\Api\Entity\MyEntity::class)->findBy(['payload'=>'sample']);
foreach ($samples as $sample) {
   var_dump($sample->getId());
} 
```

## References

You could reference other API entities via relation annotations. General bi-directional self-reference relation below:

```yml
MyVendor\Api\Entity\MyEntity:
  type: entity
  id:
    id:
      type: int

  fields:
    payload:
      type: string
    
  manyToOne:
    parent:
        target: MyVendor\Api\Entity\MyEntity
        inversedBy: children
  oneToMany:
    children:
        target: MyVendor\Api\Entity\MyEntity
        mappedBy: parent
```

In order to make `*toMany` relations works flawlessly you should define the mapped class property 
as `Entity[]|ArrayCollection` as hydrator will substitute your relation property with lazy-loading collection interface.   

### Note on lazy-loading

Generic API is not a DB, so eager reference pre-fetching will always result in additional API query, 
so current implementation always assumes that all requests are extra-lazy.
This means that no data will be fetched until you really need it, and you'll have only lazy proxy object before that happens.

Keep it in the mind  

## Hacking into fetching process

### Custom repository

Just call defined RpcClient from your repository

```php
class MyRepository extends \Bankiru\Api\Doctrine\EntityRepository {
    public function callCustomRpcMethod()
    {
        $request = new \Bankiru\Api\Rpc\RpcRequest('my-method',['param1'=>'value1']);
        $data = $this->getClient()->invoke([$request])->getResponse($request);
        
        return $data;
    }
} 
```

Or more portable with mapping

```yml
MyVendor\Api\Entity\MyEntity:
  type: entity
  id:
    id:
      type: int

  fields:
    payload:
      type: string
    
  repositoryClass: MyVendor\Api\Repository\MyRepository # This will override repository for MyEntity
  api:
    name: Vendor\Api\CrudsApiFactory
  client:
    name: my-client
    # entityPath: my-entity autoconfigures find and search methods for you as following, but it is not overridable
    # You can also specify path separator as
    # entityPathSeparator: "-"
    # To make autogenerated methods look like my-entity-find 
    methods: 
        find: my-entity\find      # find method is mandatory to find calls work
        search: my-entity\search  # find method is mandatory to findBy calls work
        custom: my-custom-method  # do some custom stuff
```

```php
class MyRepository extends \Bankiru\Api\Doctrine\EntityRepository {
    public function callCustomRpcMethod()
    {
        $request = new \Bankiru\Api\Rpc\RpcRequest(
            $this->getClientMethod('custom'),
            ['param1'=>'value1']
        );
        $data = $this->getClient()->invoke([$request])->getResponse($request);
        
        return $data;
    }
} 
```

### Custom Cruds API

### Custom field types

You could register additional field types to configuration `TypeRegistry` (`Configuration::getTypeRegistry()`). 
Just implement the `Type` and register it via `TypeRegistry::add('alias', $type)`.
Doctrine ORM types are simple transformers with no real dependencies available but for
this implementation we gone a bit further and make the types DI capable, so you can
register any instance of `Type` as type, so it could be DI-enabled service, with any logic
you need

### TBD

* No embeddables

