# Middleware 
Implements an API End-Point that servers as a handle upon which other Middleware components can declare webservice functions.

## Drupal Hooks

### * hook_mware_default_service_operations_alter($resources)
This hook is called right after the Middleware endpoint is created. 

It is passed the **$endpoint->resources** property component of the **End-Point** so that implementers can enable or disable resources.

The implementer should do something similar to the below with the parameter:

    /**
     * Implements hook_mware_default_service_operations_alter
     * @param type $endpoint
     */
    function MODULENAME_mware_default_service_operations_alter(&$resources) {

        $resources['<resource_name>'] = [
            'operations' => [
                'retrieve' => [
                    'enabled' => '1',
                ],
                'index' => [
                    'enabled' => '1',
                ],
                ... other operations elements
            ],
            ... other operations
        ];
    }
