<?php

namespace Drupal\os2web_meetings_agman\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\node\Entity\Node;
use Drupal\os2web_meetings\Entity\Meeting;
use Drupal\os2web_meetings\Form\SettingsForm;
use Drupal\os2web_meetings\Plugin\migrate\source\MeetingsDirectory;

/**
 * Source plugin for retrieving data via URLs.
 *
 * @MigrateSource(
 *   id = "os2web_meetings_directory_agman"
 * )
 */
class MeetingsDirectoryAgman extends MeetingsDirectory {

  /**
   * {@inheritdoc}
   */
  public function getMeetingsManifestPath() {
    return \Drupal::config(SettingsForm::$configName)
      ->get('agman_meetings_manifest_path');
  }

  /**
   * {@inheritdoc}
   */
  public function convertAgendaAccessToCanonical(array $source) {
   // if (strcasecmp($source['agenda_access'], 'true') === 0) {
      return MeetingsDirectory::AGENDA_ACCESS_OPEN;
   // }
   // else {
  //    return MeetingsDirectory::AGENDA_ACCESS_CLOSED;
  //  }
  }

  /**
   * {@inheritdoc}
   */
  public function convertAgendaTypeToCanonical(array $source) {
    if ((int) $source['agenda_type'] === 0) {
      return MeetingsDirectory::AGENDA_TYPE_KLADDE;
    }
    else if ((int) $source['agenda_type'] === 1) {
      return MeetingsDirectory::AGENDA_TYPE_DAGSORDEN;
    }
    else {
      return MeetingsDirectory::AGENDA_TYPE_REFERAT;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function convertStartDateToCanonical(array $source) {
    $start_date = $source['meeting_start_date'];

    return $this->convertDateToTimestamp($start_date);
  }

  /**
   * {@inheritdoc}
   */
  public function convertEndDateToCanonical(array $source) {
    if (isset($source['meeting_end_date'])) {
      $end_date = $source['meeting_end_date'];
    }
    else {
      // Reusing start date.
      $end_date = $source['meeting_start_date'];
    }

    return $this->convertDateToTimestamp($end_date);
  }

  /**
   * {@inheritdoc}
   */
  public function convertAgendaDocumentToCanonical(array $source) {

    $title = 'Samlet document';
    // There is no reference to HTML file, but we expect it to be in the
    // directory with the following name.
    $uri = 'Publication' . $source['meeting_id'] . '.pdf';

    return [
      'title' => $title,
      'uri' => $uri,
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function convertCommitteeToCanonical(array $source) {
    $id = $source['committee_id'];
    $name = $source['committee_name'];

    return [
      'id' => $id,
      'name' => $name,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function convertLocationToCanonical(array $source) {
    $name = $source['location_name'];
    // We don't have an ID for the location, use name instead.
    $id = $name;

    return [
      'id' => $id,
      'name' => $name,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function convertBulletPointsToCanonical(array $source) {
    $canonical_bullet_points = [];

    $source_bullet_points = array_pop($source['bullet_points']);

    // Dealing with one BP meeting.
    if (array_key_exists('Caption', $source_bullet_points['Item'])) {
      $source_bullet_points['Item'] = [
        0 => $source_bullet_points['Item']
      ];
    }

    foreach ($source_bullet_points['Item'] as $key => $bullet_point) {
      $id = $bullet_point['@attributes']['ID'];
      $bpNumber = isset($bullet_point['@attributes']['Nummer']) ? $bullet_point['@attributes']['Nummer'] : $key + 1;
      $title = $bullet_point['Caption'];
      $access = filter_var($bullet_point['IsPublic'], FILTER_VALIDATE_BOOLEAN);

      // Getting attachments (text).
      $source_attachments = $bullet_point;
      $canonical_attachments = [];
      if (is_array($source_attachments)) {
        $canonical_attachments = $this->convertAttachmentsToCanonical($source_attachments, $access);
      }

      // Getting enclosures (files).
      $source_enclosures = NULL;
      if (array_key_exists('Enclosures', $bullet_point)) {
        $source_enclosures = $bullet_point['Enclosures']['Enclosure'] ?? NULL;
      }
      $canonical_enclosures = [];
      if (is_array($source_enclosures)) {
       // Handling single items.
        if (array_key_exists('@attributes', $source_enclosures)) {
          $source_enclosures = [$source_enclosures];
        }

        // If access = FALSE, force all enclosures to be closed.
        if (!$access){
          foreach ($source_enclosures as &$enclosure) {
            $enclosure['IsProtected'] = TRUE;
          }
        }

        $canonical_enclosures = $this->convertEnclosuresToCanonical($source_enclosures);
      }

      $canonical_bullet_points[] = [
        'id' => $id,
        'number' => $bpNumber,
        'title' => $title,
        'access' => $access,
        'attachments' => $canonical_attachments,
        'enclosures' => $canonical_enclosures,
      ];
    }

    return $canonical_bullet_points;
  }

  /**
   * {@inheritdoc}
   */
  public function convertAttachmentsToCanonical(array $source_attachments, $access = TRUE) {
    $canonical_attachments = [];

    $closed_bpa_titles = \Drupal::config(SettingsForm::$configName)->get('agman_meetings_import_closed_bpa_titles');
    $closed_bpa_titles = !empty($closed_bpa_titles) ? explode(',', $closed_bpa_titles) : array();
    $closed_bpa_titles = array_map('trim', $closed_bpa_titles);
    $closed_bpa_titles = array_map('strtolower', $closed_bpa_titles);

    $empty_bpa_title = \Drupal::config(SettingsForm::$configName)->get('agman_meetings_import_empty_bpa_title');

    if (isset($source_attachments['Fields']['ItemField'])) {
      foreach ($source_attachments['Fields']['ItemField'] as $attachment) {
        $attachment['Caption'] = is_array($attachment['Caption']) ? $empty_bpa_title : $attachment['Caption'];

        if (!$access && !empty($closed_bpa_titles) && !in_array(strtolower($attachment['Caption']), $closed_bpa_titles)) {
          continue;
        }

        // Using title as ID, as we don't have a real one.
        if ($attachment['HasContent'] === 'True' && !empty($attachment['Content'])) {
          $id = $attachment['@attributes']['ID'];
          $title = $attachment['Caption'];
          $body = (string) $attachment['Content'];

          $canonical_attachments[] = [
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'access' => $access,
          ];
        }
      }
    }

    if (!empty($source_attachments['ItemHistory']['ItemField'])) {
      $decisionHistoryTitle = 'Beslutningshistorik';

      // Allowing Beslutningshistorik only if the access is TRUE, or it's added
      // to the whitelist.
      if ($access || (!empty($closed_bpa_titles) && in_array(strtolower($decisionHistoryTitle), $closed_bpa_titles))) {
        $source_attachments_history = $source_attachments['ItemHistory']['ItemField'];
        // Handling single items.
        if (array_key_exists('@attributes', $source_attachments_history)) {
          $source_attachments_history = [$source_attachments_history];
        }
        $body = '';
        $id = '';
        foreach ($source_attachments_history as $history) {
          if (isset($history['HasContent']) && $history['HasContent'] === 'True') {
            // It's a composed field, use the first ID it can find.
            if (empty($id)) {
              $id = $history['@attributes']['ID'];
            }

            $body .= t('@decision truffet af @comittee d. @date ', array(
              '@decision' => (string) $history['Caption'],
              '@comittee' => (string) $history['MeetingDetails']['CommitteeName'],
              '@date'     => date('d-m-Y', strtotime((string) $history['MeetingDetails']['MeetingDueDate'])),
            ));
            $body .= '<br>' . strip_tags((string) $history['Content']) . '<br>';
          }
        }

        if (!empty($id) && !empty($body)) {
          $canonical_attachments[] = [
            'id' => $id,
            'title' => $decisionHistoryTitle,
            'body' => $body,
            'access' => TRUE,
          ];
        }
      }
    }

    return $canonical_attachments;
  }

  /**
   * {@inheritdoc}
   */
  public function convertParticipantToCanonical(array $source) {
    $canonical_participants = ['participants' => [], 'participants_canceled' => []];
    $participants =  $source['participants'];
    foreach ($participants as $participant){
      $participation_note = '';
      if (!empty($participant['ParticipationNote'])) {
        $participation_note = ", " . $participant['ParticipationNote'];
      }
      if ($participant['ParticipationStatusAsValue'] == '1' || $participant['ParticipationStatusAsValue'] == '4') {
           $canonical_participants['participants'][] = (string) $participant['Name'] . " - " . $participant['ParticipationStatusAsText'] . $participation_note;
        }
        // Changed by stan@bellcom.dk 19.09.2013 - to consider participants with "Ikke bestemt" status as actual participants
        else if($participant['ParticipationStatusAsText'] == 'NotDecided' || $participant['ParticipationStatusAsValue'] == '0'){
          $canonical_participants['participants'][] = (string) $participant['Name']  . $participation_note;
        }
        else {
         $canonical_participants['participants_canceled'][] = (string) $participant['Name'] . " - " . $participant['ParticipationStatusAsText'] . $participation_note;
        }
    }
    return $canonical_participants;

  }
  /**
   * {@inheritdoc}
   */
  public function convertEnclosuresToCanonical(array $source_enclosures) {
    $canonical_enclosures = [];
    foreach ($source_enclosures as $enclosure) {

      if (is_array($enclosure)) {
        $id = $enclosure['@attributes']['ID'];
        $title = $enclosure['Name'];
        $access = !filter_var((string) $enclosure['IsProtected'], FILTER_VALIDATE_BOOLEAN);
        $uri = $enclosure['InstantiationFilePathRelative'];
        $import_closed_bilags = \Drupal::config(SettingsForm::$configName)->get('agman_meetings_import_closed_bilags');
        if (!$import_closed_bilags && $access === FALSE) {
          continue;
        }
        $canonical_enclosures[] = [
          'id' => $id,
          'title' => $title,
          'uri' => $uri,
          'access' => $access,
        ];
      }
    }
    return $canonical_enclosures;
  }

  /**
   * Converts Danish specific string date into timestamp in UTC.
   *
   * @param string $dateStr
   *   Date as string, e.g. "27. august 2018 16:00".
   *
   * @return int
   *   Timestamp in UTC.
   *
   * @throws \Exception
   */
  private function convertDateToTimestamp($dateStr) {
    $dateStr = str_ireplace([
      ". januar ",
      ". februar ",
      ". marts ",
      ". april ",
      ". maj ",
      ". juni ",
      ". juli ",
      ". august ",
      ". september ",
      ". oktober ",
      ". november ",
      ". december ",
    ],
      [
        "-1-",
        "-2-",
        "-3-",
        "-4-",
        "-5-",
        "-6-",
        "-7-",
        "-8-",
        "-9-",
        "-10-",
        "-11-",
        "-12-",
      ], $dateStr);

    $dateTime = new \DateTime($dateStr, new \DateTimeZone('Europe/Copenhagen'));

    return $dateTime->getTimestamp();
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    parent::postImport($event);

    // Find all meetings.
    $query = \Drupal::entityQuery('node')->accessCheck(false);
    $query->condition('type', 'os2web_meetings_meeting');
    $query->condition('field_os2web_m_source', $this->getPluginId());
    $entity_ids = $query->execute();

    $meetings = Node::loadMultiple($entity_ids);

    // Group meetings as:
    // $groupedMeetings[<meeting_id>][<agenda_id>] = <node_id> .
    $groupedMeetings = [];
    foreach ($meetings as $meeting) {
      $os2webMeeting = new Meeting($meeting);

      $meeting_id = $os2webMeeting->getMeetingId();
      $agenda_id = $os2webMeeting->getEsdhId();

      $groupedMeetings[$meeting_id][$agenda_id] = $os2webMeeting->id();

      // Sorting agendas, so that lowest agenda ID is always the first.
      sort($groupedMeetings[$meeting_id]);
    }

    // Process grouped meetings and set addendum fields.
    foreach ($groupedMeetings as $meeting_id => $agendas) {
      // Skipping if agenda count is 1.
      if (count($agendas) == 1) {
        continue;
      }

      $mainAgendaNodedId = array_shift($agendas);

      foreach ($agendas as $agenda_id => $node_id) {
        // Getting the meeting.
        $os2webMeeting = new Meeting($meetings[$node_id]);

        // Setting addendum field, meeting is saved inside a function.
        $os2webMeeting->setAddendum($mainAgendaNodedId);
      }
    }
  }
   /**
   * {@inheritdoc}
   */
  public function convertAgendaIdToCanonical(array $source) {
    return $source['agenda_id'];
  }
}
