<?php

// Function to fetch instructor's bandwidth usage from Bunny Stream API
function getInstructorBandwidth($instructorId, $libraryId, $apiKey) {
    // Construct the API URL to get statistics for the instructor's videos
    $url = "https://video.bunnycdn.com/library/{$libraryId}/statistics";

    // cURL setup to make the GET request to Bunny Stream API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "AccessKey: {$apiKey}"
    ]);
    
    // Execute the cURL request and capture the response
    $response = curl_exec($ch);
    curl_close($ch);

    // Check for a successful API response
    if ($response === false) {
        return false;
    }

    // Decode the JSON response
    $data = json_decode($response, true);
    
    // Check if the API response contains bandwidth information
    if (isset($data['bandwidthUsed'])) {
        return $data['bandwidthUsed']; // Bandwidth used in GB
    }

    return false;
}

// Function to calculate and display the remaining bandwidth
function getRemainingBandwidth($instructorId, $libraryId, $apiKey, $yearlyLimit = 5) {
    $bandwidthUsed = getInstructorBandwidth($instructorId, $libraryId, $apiKey);

    if ($bandwidthUsed === false) {
        echo "Error fetching bandwidth usage or API issue.\n";
        return false;
    }

    // Calculate the remaining bandwidth
    $remainingBandwidth = $yearlyLimit - $bandwidthUsed;

    // If the remaining bandwidth is less than 0, set it to 0
    if ($remainingBandwidth < 0) {
        $remainingBandwidth = 0;
    }

    echo "Instructor {$instructorId} has used {$bandwidthUsed} GB of bandwidth.\n";
    echo "Remaining bandwidth: {$remainingBandwidth} GB.\n";

    return $remainingBandwidth;
}

// Example usage: Replace these values with actual instructor ID, library ID, and API Key
$instructorId = 'instructor_123'; // Instructor ID (for tracking)
$libraryId = '360333'; // Bunny Stream Library ID
$apiKey = '63c47459-db06-45eb-9a8be9b45e92-a57d-44d3'; // Your Bunny Stream API Key

// Get the remaining bandwidth
$remainingBandwidth = getRemainingBandwidth($instructorId, $libraryId, $apiKey);

// Logic to display the remaining bandwidth in the dashboard
if ($remainingBandwidth !== false) {
    echo "Instructor's remaining bandwidth for this year: {$remainingBandwidth} GB.\n";
    // Here you can embed this output into your dashboard view
}

?>
