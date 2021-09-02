<?php

namespace iCalPhpParser;

class iCal_Parser
{
    /**
     * @var string
     */
    public $prodid;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $content;

    /**
     * @var array
     */
    public $events = array();

    /**
     * @var array
     */
    protected $_eventsByDate;


    public function __construct($content = null)
    {
        if ($content) {
            $isUrl = strpos($content, 'http') === 0 && filter_var($content, FILTER_VALIDATE_URL);
            $isFile = strpos($content, "\n") === false && file_exists($content);
            if ($isUrl || $isFile) {
                $content = file_get_contents($content);
            }
            $this->parse($content);
        }
    }


    public function prodid(): ?string
    {
        return $this->prodid;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function content(): ?string
    {
        return $this->content;
    }

    /**
     * @return iCal_Event[]|null
     */
    public function events(): ?array
    {
        return $this->events;
    }

    /**
     * @return iCal_Event[]|null
     */
    public function eventsByDate(): ?array
    {
        if (! $this->_eventsByDate) {
            $this->_eventsByDate = array();
            $tmpEventsByDate = array();

            foreach ($this->events() as $event) {
                foreach ($event->occurrences() as $occurrence) {
                    $date = date('Y-m-d', $occurrence);
                    $newevent = clone $event;
                    $newevent->fixOccurringDate($occurrence);
                    // generate key for sorting
                    $key = strtotime($newevent->dateStart);
                    while(isset($tmpEventsByDate[$date][$key])) $key++;
                    $tmpEventsByDate[$date][$key] = $newevent;
                }
            }

            // sort array
            ksort($tmpEventsByDate);
            foreach ($tmpEventsByDate as $date => $value) {
                ksort($value);
                $this->_eventsByDate[$date] = $value;
            }

            // prevent duplicates for edited dates in recurring events
            foreach ($this->_eventsByDate as $dateKey => $date) {
                foreach ($date as $event) {
                    if(!empty($event->recurrenceId)) {
                        $uid = $event->uid;

                        foreach ($date as $eventKey => $eventValue) {
                            if($eventValue->uid == $uid && (empty($eventValue->recurrenceId))) {
                                unset($this->_eventsByDate[$dateKey][$eventKey]);
                            }
                        }

                    }
                }
            }
        }

        return $this->_eventsByDate;
    }

    public function eventsByDateBetween($start, $end, int $limit=NULL): ?array
    {
        if ((string) (int) $start !== (string) $start) {
            $start = strtotime($start);
        }
        $start = date('Y-m-d', $start);

        if ((string) (int) $end !== (string) $end) {
            $end = strtotime($end);
        }
        $end = date('Y-m-d', $end);

        $return = array();
        foreach ($this->eventsByDate() as $date => $events) {
            if ($start <= $date && $date < $end) {
                if(empty($limit) || count($return) <= $limit) {
                    $return[$date] = $events;
                }
            }
            if(!empty($limit) && count($return) >= $limit){
                break;
            }
        }

        return $return;
    }

    public function eventsByDateSince($start, int $limit=NULL): ?array
    {
        if ((string) (int) $start !== (string) $start) {
            $start = strtotime($start);
        }
        $start = date('Y-m-d', $start);

        $return = array();
        foreach ($this->eventsByDate() as $date => $events) {
            if ($start <= $date) {
                if(empty($limit) || count($return) <= $limit) {
                    $return[$date] = $events;
                }
            }
            if(!empty($limit) && count($return) >= $limit){
                break;
            }
        }

        return $return;
    }

    public function eventsByDateUntil($end, int $limit=NULL): ?array
    {
        if ((string) (int) $end !== (string) $end) {
            $end = strtotime($end);
        }

        $start = date('Y-m-d');
        $end = date('Y-m-d', $end);
        $return = array();
        foreach ($this->eventsByDate() as $date => $events) {
            if ($start <= $date && $end >= $date) {
                if(empty($limit) || count($return) <= $limit) {
                    $return[$date] = $events;
                }
            }
            if(!empty($limit) && count($return) >= $limit){
                break;
            }
        }
        return $return;
    }

    public function parse($content): iCal_Parser
    {
        $content = str_replace("\r\n ", '', $content);

        $this->content = $content;

        // ProdId
        preg_match('`^PRODID:-//(.*)//(.*)$`m', $content, $m);
        $this->prodid = $m ? trim($m[1]) : null;

        // Title
        preg_match('`^X-WR-CALNAME:(.*)$`m', $content, $m);
        $this->title = $m ? trim($m[1]) : null;

        // Description
        preg_match('`^X-WR-CALDESC:(.*)$`m', $content, $m);
        $this->description = $m ? trim($m[1]) : null;

        // Events
        preg_match_all('`BEGIN:VEVENT(.+)END:VEVENT`Us', $content, $m);
        foreach ($m[0] as $c) {
            $this->events[] = new iCal_Event($c);
        }

        return $this;
    }
}