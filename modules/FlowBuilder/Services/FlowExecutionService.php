<?php

namespace Modules\FlowBuilder\Services;

use App\Helpers\CustomHelper;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\WhatsappService;
use Modules\FlowBuilder\Models\Flow;
use Modules\FlowBuilder\Models\FlowLog;
use Modules\FlowBuilder\Models\FlowMedia;
use Modules\FlowBuilder\Models\FlowUserData;

class FlowExecutionService
{
    private $whatsappService;
    private $organizationId;

    public function __construct($organizationId)
    {
        $this->organizationId = $organizationId;
        $this->initializeWhatsappService();
    }

    private function initializeWhatsappService()
    {
        $config = Organization::where('id', $this->organizationId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];

        $accessToken = $config['whatsapp']['access_token'] ?? null;
        $apiVersion = config('graph.api_version');
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $this->whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $this->organizationId);
    }
    
    /**
     * Execute a flow for a user based on their input.
     *
     * @param $chat
     * @param boolean $isNewContact
     * @param string $message
     * @return FlowStep|null
     */
    public function executeFlow($chat, $isNewContact, $message)
    {
        try {
            \Log::channel('daily')->info('-------- FLOW EXECUTION START --------');
            \Log::channel('daily')->info('Executing flow for contact ID: ' . $chat->contact_id . ', message: ' . $message);
            \Log::channel('daily')->info('Is new contact: ' . ($isNewContact ? 'yes' : 'no'));

            if(CustomHelper::isModuleEnabled('Flow builder', $chat->organization_id)){
                \Log::channel('daily')->info('Flow builder module is enabled for organization: ' . $chat->organization_id);
                
                // Find the current step for the user in the flow
                $flowData = FlowUserData::firstOrNew(['contact_id' => $chat->contact_id]);
                \Log::channel('daily')->info('Current flow data: ' . json_encode($flowData->toArray()));
                
                $flowId = null;

                if(!$flowData->exists){
                    \Log::channel('daily')->info('No existing flow data, checking for matching flow');
                    // Determine the flow based on trigger type
                    $flowQuery = Flow::where('organization_id', $chat->organization_id)->where('status', 'active');
                    $flow = null;

                    //Check if any flow trigger has been hit
                    if($isNewContact){
                        \Log::channel('daily')->info('New contact, checking for new_contact trigger');
                        $flow = $flowQuery->where('trigger', 'new_contact')->first();
                        
                        if ($flow) {
                            \Log::channel('daily')->info('Found new_contact flow: ' . $flow->name . ' (ID: ' . $flow->id . ')');
                        } else {
                            \Log::channel('daily')->info('No new_contact flow found');
                        }
                    } else {
                        \Log::channel('daily')->info('Existing contact, checking for keyword match');
                        $msg = strtolower(trim($message)); // Normalize the message
                        $words = explode(' ', $msg); // Split message into individual words
                        
                        \Log::channel('daily')->info('Message words: ' . json_encode($words));
                        
                        $conditions = [];
                        foreach ($words as $word) {
                            // Remove extra spaces and make sure the word is trimmed properly
                            $word = strtolower(trim($word));
                            $conditions[] = "FIND_IN_SET(?, REPLACE(keywords, ' ', ''))"; // Strip spaces from keywords in the DB
                        }

                        $flow = \DB::table('flows')->whereRaw(
                            '( `trigger` = ? AND organization_id = ?) AND (' . implode(' OR ', $conditions) . ')',
                            array_merge(
                                ['keywords', $chat->organization_id], // Bind values for `trigger` and `organization_id`
                                array_map(function($word) {
                                    return strtolower(trim($word)); // Normalize the word before comparison
                                }, $words)
                            )
                        )->first();

                        if ($flow) {
                            \Log::channel('daily')->info('Found keyword flow: ' . $flow->name . ' (ID: ' . $flow->id . ')');
                            \Log::channel('daily')->info('Keywords: ' . $flow->keywords);
                        } else {
                            \Log::channel('daily')->info('No keyword flow found');
                            
                            // Log all active flows for debugging
                            $activeFlows = \DB::table('flows')
                                ->where('organization_id', $chat->organization_id)
                                ->where('status', 'active')
                                ->get();
                            
                            \Log::channel('daily')->info('Active flows: ' . json_encode($activeFlows));
                        }
                    }

                    // Set the flow ID if a matching flow is found
                    if ($flow) {
                        $flowId = $flow->id;
                        \Log::channel('daily')->info('Matching flow found with ID: ' . $flowId);
                    }

                    // If a flow ID was found, create a new FlowUserData record
                    if ($flowId) {
                        $flowData->fill([
                            'flow_id' => $flowId,
                            'current_step' => 1
                        ])->save();
                        \Log::channel('daily')->info('Created new flow data: ' . json_encode($flowData->toArray()));
                    }
                } else {
                    $flowId = $flowData->flow_id;
                    \Log::channel('daily')->info('Using existing flow data with flow ID: ' . $flowId);
                }

                if ($flowId) {
                    \Log::channel('daily')->info('Starting flow processing with ID: ' . $flowId);
                    $result = $this->processFlow($chat, $isNewContact, $flowData, $chat->contact_id, strtolower(trim($message)));
                    \Log::channel('daily')->info('Flow processing result: ' . ($result ? 'success' : 'failure'));
                    
                    return $result;
                } else {
                    \Log::channel('daily')->info('No matching flow found, skipping flow processing');
                }
            } else {
                \Log::channel('daily')->info('Flow builder module is not enabled for organization: ' . $chat->organization_id);
            }
            
            \Log::channel('daily')->info('-------- FLOW EXECUTION END --------');
        } catch (\Exception $e) {
            \Log::channel('daily')->error('Flow execution error: ' . $e->getMessage());
            \Log::channel('daily')->error('Stack trace: ' . $e->getTraceAsString());
        }
        
        return false;
    }

    public function hasActiveFlow($chat){
        $activeFlow = FlowUserData::where('contact_id', $chat->contact_id)->first();

        return $activeFlow ? true : false;
    }

    public function processFlow($chat, $isNewContact, $flowData, $contactId, $message){
        try {
            \Log::channel('daily')->info('-------- FLOW PROCESSING START --------');
            \Log::channel('daily')->info('Processing flow for contact: ' . $contactId . ' with message: ' . $message);
            \Log::channel('daily')->info('Current step: ' . $flowData->current_step);
            
            $nextStep = $flowData->current_step + 1;
            $flow = Flow::find($flowData->flow_id);

            if (!$flow || empty($flow->metadata)) {
                \Log::channel('daily')->error('Flow not found or metadata is empty');
                \Log::channel('daily')->info('-------- FLOW PROCESSING END --------');
                return false;
            }

            \Log::channel('daily')->info('Flow found: ' . $flow->name . ' (ID: ' . $flow->id . ')');
            \Log::channel('daily')->info('Flow status: ' . $flow->status);
            
            $edgesArray = json_decode($flow->metadata, true);
            $edges = \Arr::get($edgesArray, "edges", null);
            
            \Log::channel('daily')->info('Flow has ' . count($edges) . ' edges');
            
            // Get the current node to check its type
            $currentNode = null;
            foreach ($edgesArray['nodes'] ?? [] as $node) {
                if ($node['id'] == $flowData->current_step) {
                    $currentNode = $node;
                    break;
                }
            }

            if ($currentNode) {
                \Log::channel('daily')->info('Current node: ' . json_encode([
                    'id' => $currentNode['id'],
                    'type' => $currentNode['type'] ?? 'unknown',
                    'fields' => $currentNode['data']['metadata']['fields'] ?? []
                ]));
            } else {
                \Log::channel('daily')->warning('Current node not found for step: ' . $flowData->current_step);
            }

            // If current node is a button node, we need to match the button text
            $isButtonNode = isset($currentNode['data']['metadata']['fields']['type']) && 
                           $currentNode['data']['metadata']['fields']['type'] === 'interactive buttons';
            
            \Log::channel('daily')->info('Is button node: ' . ($isButtonNode ? 'yes' : 'no'));
            
            if ($isButtonNode) {
                $buttons = $currentNode['data']['metadata']['fields']['buttons'] ?? [];
                \Log::channel('daily')->info('Button node buttons: ' . json_encode($buttons));
            }

            $metadataArray = $this->findEdgesBySource($edges, $flowData->current_step, $message);
            
            if(empty($metadataArray)){
                \Log::channel('daily')->warning('No matching edge found using findEdgesBySource');
                
                // If we're on a button node and no match was found, try to find a matching button
                if ($isButtonNode) {
                    \Log::channel('daily')->info('Trying direct button text matching');
                    
                    foreach ($edges as $edge) {
                        if ($edge['source'] == $flowData->current_step) {
                            \Log::channel('daily')->info('Checking edge: source=' . $edge['source'] . ', sourceHandle=' . ($edge['sourceHandle'] ?? 'none'));
                            
                            $buttonText = $edge['sourceNode']['data']['metadata']['fields']['buttons']['button' . $edge['sourceHandle']] ?? '';
                            \Log::channel('daily')->info('Button text: "' . $buttonText . '", Message: "' . $message . '"');
                            
                            if (strtolower(trim($message)) === strtolower(trim($buttonText))) {
                                \Log::channel('daily')->info('Button text match found!');
                                $metadataArray = $edge['targetNode'] ?? [];
                                break;
                            }
                        }
                    }
                }

                // If still no match, reset the flow
                if (empty($metadataArray)) {
                    \Log::channel('daily')->info('No matching edge found, resetting flow');
                    FlowUserData::where('contact_id', $contactId)->delete();
                    \Log::channel('daily')->info('Flow user data deleted, restarting flow execution');
                    $this->executeFlow($chat, $isNewContact, $message);
                    \Log::channel('daily')->info('-------- FLOW PROCESSING END --------');
                    return false;
                }
            } else {
                \Log::channel('daily')->info('Matching edge found using findEdgesBySource');
            }

            \Log::channel('daily')->info('Next node: ' . json_encode([
                'id' => $metadataArray['id'] ?? 'unknown',
                'type' => $metadataArray['type'] ?? 'unknown'
            ]));

            $fieldsArray = \Arr::get($metadataArray, "data.metadata.fields", null);
            $type = $fieldsArray['type'] ?? null;

            \Log::channel('daily')->info('Next node type: ' . $type);

            $contact = Contact::find($contactId);
            if (!$contact) {
                \Log::channel('daily')->error('Contact not found: ' . $contactId);
                \Log::channel('daily')->info('-------- FLOW PROCESSING END --------');
                return false;
            }

            \Log::channel('daily')->info('Contact found: ' . $contact->first_name . ' ' . $contact->last_name);

            $message = $this->replacePlaceholders($contact->uuid, $fieldsArray['body'] ?? '');

            // Initialize the header array if needed for interactive messages
            $header = $this->prepareHeader($fieldsArray);
            $buttonArray = [];
            $buttonType = null;
            $buttonLabel = null;

            if($type == 'text'){
                $buttonType = 'text';
            } elseif ($type === 'interactive buttons') {
                $buttonType = ($fieldsArray['buttonType'] ?? '') === 'buttons'
                    ? 'interactive buttons'
                    : 'interactive call to action url';
                $buttonArray = $this->prepareButtonArray($fieldsArray, $buttonType);
                \Log::channel('daily')->info('Prepared button array: ' . json_encode($buttonArray));
            } elseif ($type === 'interactive list') {
                $buttonType = 'interactive list';
                $buttonArray = $this->prepareButtonArray($fieldsArray, $buttonType);
                $buttonLabel = $fieldsArray['buttonLabel'];
            }

            $response = null;

            switch ($type) {
                case 'text':
                    \Log::channel('daily')->info('Sending text message: ' . $message);
                    $response = $this->whatsappService->sendMessage($contact->uuid, $message, 0, $buttonType);
                    break;

                case 'media':
                    $mediaInfo = json_decode($fieldsArray['media']['metadata'] ?? '{}', true);
                    $mediaLocation = $fieldsArray['media']['location'] ?? '';
                    $mediaLocation = ($mediaLocation === 'aws') ? 'amazon' : $mediaLocation;

                    \Log::channel('daily')->info('Sending media message: ' . ($fieldsArray['mediaType'] ?? 'unknown') . ', path: ' . ($fieldsArray['media']['path'] ?? 'none'));
                    $response = $this->whatsappService->sendMedia(
                        $contact->uuid,
                        $fieldsArray['mediaType'] ?? '',
                        $mediaInfo['name'] ?? '',
                        $fieldsArray['media']['path'] ?? '',
                        $fieldsArray['media']['path'] ?? '',
                        $mediaLocation,
                        $fieldsArray['caption'] ?? ''
                    );
                    break;

                case 'interactive buttons':
                case 'interactive list':
                    \Log::channel('daily')->info('Sending interactive message with type: ' . $buttonType);
                    \Log::channel('daily')->info('Button data: ' . json_encode([
                        'message' => $message,
                        'buttonType' => $buttonType,
                        'buttons' => $buttonArray,
                        'header' => $header,
                        'footer' => $fieldsArray['footer'] ?? ''
                    ]));
                    
                    $response = $this->whatsappService->sendMessage(
                        $contact->uuid,
                        $message,
                        0,
                        $buttonType,
                        $buttonArray,
                        $header,
                        $fieldsArray['footer'] ?? '',
                        $buttonLabel
                    );
                    break;
            }

            if($response){
                \Log::channel('daily')->info('Message sent successfully, updating flow step to: ' . $metadataArray['id']);
                \Log::channel('daily')->info('Response: ' . json_encode($response));
                
                FlowUserData::where('contact_id', $contactId)->update(['current_step' => $metadataArray['id']]);
                
                if(isset($response->data->chat->id)){
                    FlowLog::create([
                        'flow_id' => $flowData->flow_id,
                        'chat_id' => $response->data->chat->id
                    ]);
                    \Log::channel('daily')->info('Created flow log for chat ID: ' . $response->data->chat->id);
                    \Log::channel('daily')->info('-------- FLOW PROCESSING END --------');
                    return true;
                } else {
                    \Log::channel('daily')->error('Response missing chat ID: ' . json_encode($response));
                }
            } else {
                \Log::channel('daily')->error('Failed to send message');
            }

            \Log::channel('daily')->info('-------- FLOW PROCESSING END --------');
            return false;
        } catch (\Exception $e) {
            \Log::channel('daily')->error('Flow processing error: ' . $e->getMessage());
            \Log::channel('daily')->error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    private function prepareHeader(array $fieldsArray): array
    {
        $header = [];

        if (($fieldsArray['headerType'] ?? '') === 'text') {
            $header = [
                'type' => 'text',
                'text' => clean($fieldsArray['headerText'] ?? ''),
            ];
        } elseif (($fieldsArray['headerType'] ?? '') !== 'none') {
            $header['type'] = $fieldsArray['headerType'] ?? '';
            $header[$fieldsArray['headerType'] ?? ''] = [
                'link' => $fieldsArray['headerMedia']['path'] ?? '',
            ];
        }

        return $header;
    }

    private function prepareButtonArray(array $fieldsArray, string $type): array
    {
        $buttonArray = [];

        if ($type === 'interactive buttons') {
            foreach ($fieldsArray['buttons'] ?? [] as $id => $title) {
                if (!empty($title)) {
                    $buttonArray[] = [
                        'id' => $id,
                        'title' => $title,
                    ];
                }
            }
        } elseif ($type === 'interactive call to action url') {
            $buttonArray = [
                'display_text' => $fieldsArray['ctaUrlButton']['displayText'] ?? '',
                'url' => $fieldsArray['ctaUrlButton']['url'] ?? '',
            ];
        } elseif ($type === 'interactive list') {
            $buttonArray = collect($fieldsArray['sections'] ?? [])->map(function ($section) {
                return [
                    'title' => $section['title'] ?? '',
                    'rows' => collect($section['rows'] ?? [])->map(function ($row) {
                        return [
                            'id' => $row['id'] ?? '',
                            'title' => $row['title'] ?? '',
                            'description' => $row['description'] ?? '',
                        ];
                    })->all()
                ];
            })->all();
        }

        return $buttonArray;
    }

    private function findEdgesBySource($edges, $sourceId, $message)
    {
        try {
            \Log::channel('daily')->info('Finding edges from source: ' . $sourceId . ' for message: ' . $message);
            
            // Convert $sourceId to a string to handle loose type matching
            $sourceId = (string) $sourceId;
            
            // Initialize an empty array to store matching edges
            $matchingEdges = [];

            // Iterate over each edge to find matches with sourceId
            foreach ($edges as $index => $edge) {
                // Check if 'source' key exists and matches the source ID
                if (isset($edge['source']) && (string) $edge['source'] === $sourceId) {
                    \Log::channel('daily')->info('Found edge with matching source: ' . json_encode([
                        'source' => $edge['source'],
                        'sourceHandle' => $edge['sourceHandle'] ?? 'none',
                        'target' => $edge['target'] ?? 'none'
                    ]));
                    
                    // For button responses, check if the message matches the button text
                    if (isset($edge['sourceNode']['data']['metadata']['fields']['type']) && 
                        $edge['sourceNode']['data']['metadata']['fields']['type'] === 'interactive buttons') {
                        
                        $buttonText = $edge['sourceNode']['data']['metadata']['fields']['buttons']['button' . $edge['sourceHandle']] ?? '';
                        \Log::channel('daily')->info('Button edge: comparing "' . $buttonText . '" with "' . $message . '"');
                        
                        if (strtolower(trim($message)) === strtolower(trim($buttonText))) {
                            \Log::channel('daily')->info('Button text match found!');
                            $matchingEdges[] = $edge;
                        }
                    } else {
                        \Log::channel('daily')->info('Non-button edge, adding to matches');
                        $matchingEdges[] = $edge;
                    }
                }
            }

            \Log::channel('daily')->info('Found ' . count($matchingEdges) . ' matching edges');

            if (count($matchingEdges) === 1) {
                \Log::channel('daily')->info('Single edge match, returning target node');
                return $matchingEdges[0]['targetNode'] ?? [];
            } else if (count($matchingEdges) > 1) {
                $firstEdge = $matchingEdges[0];
                $type = $firstEdge['sourceNode']['data']['metadata']['fields']['type'] ?? null;
                
                \Log::channel('daily')->info('Multiple edge matches, node type: ' . $type);

                // Perform logic based on the 'type'
                if ($type === 'interactive buttons') {
                    \Log::channel('daily')->info('Processing multiple button edges');
                    // For button responses, return the edge that matches the button text
                    foreach ($matchingEdges as $edge) {
                        $buttonText = $edge['sourceNode']['data']['metadata']['fields']['buttons']['button' . $edge['sourceHandle']] ?? '';
                        \Log::channel('daily')->info('Comparing button: "' . $buttonText . '" with message: "' . $message . '"');
                        
                        if (strtolower(trim($message)) === strtolower(trim($buttonText))) {
                            \Log::channel('daily')->info('Button match found, returning target node');
                            return $edge['targetNode'] ?? [];
                        }
                    }
                    
                    \Log::channel('daily')->warning('No matching button found among multiple edges');
                }
            }

            \Log::channel('daily')->info('No matching edge found, returning empty array');
            return [];
        } catch (\Exception $e) {
            \Log::channel('daily')->error('Error in findEdgesBySource: ' . $e->getMessage());
            \Log::channel('daily')->error('Stack trace: ' . $e->getTraceAsString());
            return [];
        }
    }

    private function replacePlaceholders($contactUuid, $message){
        $organization = Organization::where('id', $this->organizationId)->first();
        $contact = Contact::with('contactGroup')->where('uuid', $contactUuid)->first();
        $address = $contact->address ? json_decode($contact->address, true) : [];
        $metadata = $contact->metadata ? json_decode($contact->metadata, true) : [];
        $full_address = ($address['street'] ?? Null) . ', ' .
                        ($address['city'] ?? Null) . ', ' .
                        ($address['state'] ?? Null) . ', ' .
                        ($address['zip'] ?? Null) . ', ' .
                        ($address['country'] ?? Null);

        $data = [
            'first_name' => $contact->first_name ?? Null,
            'last_name' => $contact->last_name ?? Null,
            'full_name' => $contact->full_name ?? Null,
            'email' => $contact->email ?? Null,
            'phone' => $contact->phone ?? Null,
            'group' => $contact->contactGroup->name ?? Null,
            'organization_name' => $organization->name,
            'full_address' => $full_address,
            'street' => $address['street'] ?? Null,
            'city' => $address['city'] ?? Null,
            'state' => $address['state'] ?? Null,
            'zip_code' => $address['zip'] ?? Null,
            'country' => $address['country'] ?? Null,
        ];

        $transformedMetadata = [];
        if($metadata){
            foreach ($metadata as $key => $value) {
                $transformedKey = strtolower(str_replace(' ', '_', $key));
                $transformedMetadata[$transformedKey] = $value;
            }
        }

        $mergedData = array_merge($data, $transformedMetadata);

        //Log::info($mergedData);

        return preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($mergedData) {
            $key = $matches[1];
            return isset($mergedData[$key]) ? $mergedData[$key] : $matches[0];
        }, $message);
    }
}