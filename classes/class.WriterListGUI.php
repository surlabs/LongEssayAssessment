<?php

namespace ILIAS\Plugin\LongEssayAssessment\WriterAdmin;

use Exception;
use ILIAS\Plugin\LongEssayAssessment\Data\Essay\Essay;
use ILIAS\Plugin\LongEssayAssessment\Data\Writer\Writer;
use ILIAS\Plugin\LongEssayAssessment\LongEssayAssessmentDI;
use ILIAS\UI\Component\Symbol\Icon\Icon;
use ILIAS\Plugin\LongEssayAssessment\Data\Task\Location;
use ILIAS\UI\Component\Input\Container\Filter\Standard;
use DOMDocument;
use ILIAS\UI\Component\Image\Image;

abstract class WriterListGUI
{
    const FILTER_YES= "1";
    const FILTER_NO = "2";

    /**
     * @var Essay[]
     */
    protected $essays = [];

    /**
     * @var \ILIAS\Plugin\LongEssayAssessment\Data\Writer\Writer[]
     */
    protected $writers = [];

    protected $user_ids = [];
    /**
     * @var array
     */
    protected $user_data = [];

    /**
     * @var Location[]
     */
    private array $locations = [];

    /**
     * @var bool
     */
    protected $user_data_loaded = false;

    protected \ILIAS\UI\Factory $uiFactory;
    protected \ilCtrl $ctrl;
    protected \ilLongEssayAssessmentPlugin $plugin;
    protected \ILIAS\UI\Renderer $renderer;
    protected object $parent;
    protected string $parent_cmd;
    protected \ilUIService $ui_service;

    /** @var LongEssayAssessmentDI  */
    protected $localDI;


    public function __construct(object $parent, string $parent_cmd, \ilLongEssayAssessmentPlugin $plugin)
    {
        global $DIC;
        $this->parent = $parent;
        $this->parent_cmd = $parent_cmd;
        $this->uiFactory = $DIC->ui()->factory();
        $this->ctrl = $DIC->ctrl();
        $this->plugin = $plugin;
        $this->renderer = $DIC->ui()->renderer();
        $this->localDI = LongEssayAssessmentDI::getInstance();
        $this->ui_service = $DIC->uiService();
    }

    abstract public function getContent():string;


    /**
     * @return Essay[]
     */
    public function getEssays(): array
    {
        return $this->essays;
    }

    /**
     * @param Essay[] $essays
     */
    public function setEssays(array $essays): void
    {
        foreach ($essays as $essay) {
            $this->essays[$essay->getWriterId()] = $essay;
            $this->user_ids[] = $essay->getCorrectionFinalizedBy();
            $this->user_ids[] = $essay->getWritingAuthorizedBy();
            $this->user_ids[] = $essay->getWritingExcludedBy();
        }
    }

    /**
     * @return \ILIAS\Plugin\LongEssayAssessment\Data\Writer\Writer[]
     */
    public function getWriters(): array
    {
        return $this->writers;
    }

    /**
     * @param Writer[] $writers
     */
    public function setWriters(array $writers): void
    {
        $this->writers = $writers;

        foreach($writers as $writer) {
            $this->user_ids[] = $writer->getUserId();
        }
    }

    /**
     * Get Username
     *
     * @param int $user_id
     * @return mixed|string
     */
    private function getUsername(int $user_id)
    {
        if(!$this->user_data_loaded) {
            throw new Exception("getUsername was called without loading usernames.");
        }

        if(isset($this->user_data[$user_id])) {
            return $this->user_data[$user_id];
        } elseif (!empty($fullname = \ilObjUser::_lookupFullname($user_id))) {
            return $fullname;
        }

        return ' - ';
    }

    protected function getUsernameLink(int $user_id)
    {
        $username = $this->getUsername($user_id);
        preg_match('/href="(.+?)"/', $username, $matches);
        $href = $matches[1] ?? "";
        $label = strip_tags($username);

        return $href !== ""
            ? $this->uiFactory->link()->standard($label, $href)
            : $label;
    }

    /**
     * @param $user_id
     * @return string
     * @throws Exception
     */
    protected function getUsernameText(int $user_id): string
    {
        return strip_tags($this->getUsername($user_id));
    }

    /**
     * @param Writer $writer
     * @return \ILIAS\UI\Component\Link\Standard|string
     */
    protected function getWriterNameLink(Writer $writer)
    {
        return $this->getUsernameLink($writer->getUserId());
    }

    /**
     * @param Writer $writer
     * @return string
     */
    protected function getWriterNameText(Writer $writer): string
    {
        return $this->getUsernameText($writer->getUserId());
    }


    /**
     * Get Writer Profile Picture
     *
     * @param \ILIAS\Plugin\LongEssayAssessment\Data\Writer\Writer $writer
     * @return Icon
     * @throws Exception
     */
    protected function getWriterIcon(Writer $writer): Icon
    {
        return $this->getUserIcon($writer->getUserId());
    }

    /**
     * Get User Profile Picture
     *
     * @param int $user_id
     * @return Icon
     */
    protected function getUserIcon(int $user_id): Icon
    {
        $name = $this->getUsername($user_id, false);
        preg_match('/src="(.+?)"/', $name, $matches);
        $src = $matches[1] ?? "";
        $label = $this->plugin->txt("icon_label") . " " . strip_tags($name);

        return $src !== ""
            ? $this->uiFactory->symbol()->icon()->custom($src, $label, "medium")
            : $this->uiFactory->symbol()->icon()->standard("usr", "", "medium");
    }

    /**
     * Get User Profile Picture
     *
     * @param int $user_id
     * @return Icon
     */
    protected function getUserImage(int $user_id): ?Image
    {
        $name = $this->getUsername($user_id, false);
        preg_match('/src="(.+?)"/', $name, $matches);
        $src = $matches[1] ?? "";
        $label = $this->plugin->txt("icon_label") . " " . strip_tags($name);

        return $src !== ""
            ? $this->uiFactory->image()->standard($src, $label)
            : null;
    }

    /**
     * Load needed Usernames From DB
     * @return void
     */
    protected function loadUserData()
    {
        $back = $this->ctrl->getLinkTarget($this->parent);
        $this->user_data = \ilUserUtil::getNamePresentation(array_unique($this->user_ids), true, true, $back, true);
        $this->user_data_loaded = true;
    }

    /**
     * @param callable|null $custom_sort Custom sortation callable. Equal writer will be sorted by name.
     * @return void
     */
    protected function sortWriter(callable $custom_sort = null)
    {
        $this->sortWriterOrCorrector($this->writers, $custom_sort);
    }

    protected function getExportStepsTarget(Writer $writer)
    {
        $this->ctrl->setParameter($this->parent, "writer_id", $writer->getId());
        return $this->ctrl->getLinkTarget($this->parent, "exportSteps");
    }

    /**
     * @param callable|null $custom_sort Custom sortation callable. Equal writer will be sorted by name.
     * @return void
     */
    protected function sortWriterOrCorrector(array &$target_array, callable $custom_sort = null)
    {
        if(!$this->user_data_loaded) {
            throw new Exception("sortWriterOrCorrector was called without loading usernames.");
        }

        $names = [];
        foreach ($this->user_data as $usr_id => $name) {
            $names[$usr_id] = strip_tags($name);
        }

        $by_name = function ($a, $b) use ($names) {
            $name_a = array_key_exists($a->getUserId(), $names) ? $names[$a->getUserId()] : "ÿ";
            $name_b = array_key_exists($b->getUserId(), $names) ? $names[$b->getUserId()] : "ÿ";

            return strcasecmp($name_a, $name_b);
        };

        if($custom_sort !== null) {
            $by_custom = function ($a, $b) use ($custom_sort, $by_name) {
                $rating = $custom_sort($a, $b);
                return $rating !== 0 ? $rating :  $by_name($a, $b);
            };

            usort($target_array, $by_custom);
        } else {
            usort($target_array, $by_name);
        }
    }

    /**
     * @param Location[] $locations
     * @return void
     */
    public function setLocations(array $locations)
    {
        foreach($locations as $location) {
            $this->locations[$location->getId()] = $location;
        }
    }

    /**
     * @return Location[]
     */
    public function getLocations(): array
    {
        return $this->locations;
    }

    /**
     * @param Writer $writer
     * @return string
     */
    protected function location(Writer $writer): string
    {
        if(isset($this->essays[$writer->getId()]) &&
            ($location = $this->essays[$writer->getId()]->getLocation()) !== null &&
            isset($this->locations[$location])) {
            return $this->locations[$location]->getTitle();
        }
        return " - ";
    }

    protected function word_count(Writer $writer)
    {
        $essay = $this->essays[$writer->getId()] ?? null;
        return str_word_count($essay !== null ? ($essay->getWrittenText() ?? "") : "");
    }

    protected function filterInputs(): array
    {
        return [];
    }

    protected function filterInputActivation(): array
    {
        return [];
    }
    protected function filterItems(array $filter, Writer $writer): bool
    {
        return true;
    }

    public function filterForm(): Standard
    {
        $link = $this->ctrl->getLinkTarget($this->parent, $this->parent_cmd);
        $locations = [];

        foreach($this->getLocations() as $location) {
            $locations[$location->getId()] = (string) $location->getTitle();
        }

        $more_than_txt = $this->plugin->txt("filter_words_more_than");
        $more_than = function (int $x) use ($more_than_txt): string {
            return sprintf($more_than_txt, $x);
        };
        $less_than_txt = $this->plugin->txt("filter_words_less_than");
        $less_than = function (int $x) use ($less_than_txt): string {
            return sprintf($less_than_txt, $x);
        };

        $filter = [];
        $filter["name"] = $this->uiFactory->input()->field()->text($this->plugin->txt("participants"));
        $filter["location"] = $this->uiFactory->input()->field()->multiselect($this->plugin->txt("locations"), $locations);
        $filter["authorized"] = $this->uiFactory->input()->field()->select(
            $this->plugin->txt("filter_authorized"),
            [self::FILTER_YES => $this->plugin->txt("yes"), self::FILTER_NO => $this->plugin->txt("no")]
        );
        $filter["words"] = $this->uiFactory->input()->field()->select(
            $this->plugin->txt("filter_words"),
            ["m100" => $more_than(100), "m50" => $more_than(50), "m10" => $more_than(10),
             "l10" => $less_than(10), "l50" => $less_than(50), "l100" => $less_than(100)]
        );

        return $this->ui_service->filter()->standard(
            "abc1",
            $link,
            array_merge($filter, $this->filterInputs()),
            array_merge([true, true, true, true], $this->filterInputActivation()),
            true,
            true
        );
    }

    public function filter(array $filter, Writer $writer): bool
    {
        if(!empty($filter["name"]) && strlen($filter["name"]) > 3) {
            $names = $writer->getPseudonym() . $this->getUsernameText($writer->getUserId());
            if(!str_contains($names, $filter["name"])) {
                return false;
            }
        }
        $essay = $this->essays[$writer->getId()] ?? null;

        if(!empty($filter["location"])) {

            if($essay === null || !in_array((string)$essay->getLocation(), $filter["location"])) {
                return false;
            }
        }
        if(!empty($filter["authorized"]) && $filter["authorized"] == self::FILTER_YES) {
            if($essay === null || $essay->getWritingAuthorized() === null) {
                return false;
            }
        }
        if(!empty($filter["authorized"]) && $filter["authorized"] == self::FILTER_NO) {
            if($essay !== null && $essay->getWritingAuthorized() !== null) {
                return false;
            }
        }
        if(!empty($filter["words"])) {
            $words = $this->word_count($writer);
            switch($filter["words"]) {
                case "m100": if($words <= 100) {
                    return false;
                } break;
                case "m50": if($words <= 50) {
                    return false;
                } break;
                case "m10": if($words <= 10) {
                    return false;
                } break;
                case "l10": if($words >= 10) {
                    return false;
                } break;
                case "l50": if($words >= 50) {
                    return false;
                } break;
                case "l100": if($words >= 100) {
                    return false;
                } break;
            }
        }

        return $this->filterItems($filter, $writer);
    }
}
