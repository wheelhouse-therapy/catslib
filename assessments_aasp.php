<?php
class AssessmentData_AASP extends AssessmentData
{
    private $raRange = array(
        "Q1" => array("3","6","12","15","21","23","36","37","39","41","44","45","52","55","59"),
        "Q2" => array("2","4","8","10","14","17","19","28","30","32","40","42","47","50","58"),
        "Q3" => array("7","9","13","16","20","22","25","27","31","33","34","48","51","54","60"),
        "Q4" => array("1","5","11","18","24","26","29","35","38","43","46","49","53","56","57"),
        "taste" => array(1,2,3,4,5,6,7,8),
        "vestibular" => array(9,10,11,12,13,14,15,16),
        "visual" => array(17,18,19,20,21,22,23,24,25,26),
        "tactile" => array(27,28,29,30,31,32,33,34,35,36,37,38,39),
        "auditory" => array(50,51,52,53,54,55,56,57,58,59,60)
    );

    private $items = array(
        "I leave or move to another section when I smell a strong odor in a store (for example, bath products, perfumes)",
        "I add spice to my food",
        "I don't smell things that other people say they smell",
        "I enjoy being close to people who wear perfume or cologne",
        "I only eat familiar foods",
        "Many foods taste bland to me (in other words, food tastes plain or does not have a lot of flavour).",
        "I don't like strong tasting mints or candies (for example, hot/cinnamon or sour candy).",
        "I go over to smell fresh flowers when I see them.",
	    "I'm afraid of heights.",
	    "I enjoy how it feels to move about (for example, dancing, running)",
	    "I avoid elevators and/or escalators because I dislike the movement.",
	    "I trip or bump into things.",
	    "I dislike the movement of riding in a car.",
	    "I choose to engage in physical activities.",
	    "I am unsure of footing when walking on stairs (for example, I trip, lose balance, and/or need to hold the rail).",
	    "I become dizzy easily (for example, after bending over, getting up too fast).",
	    "I like to go to places that have bright lights and that are colourful.",
	    "I keep the shades down during the day when I am home.",
	    "I like to wear colourful clothing.",
	    "I become frustrated when trying to find something in a crowded drawer or messy room.",
	    "I miss the street, building, or room signs when trying to go somewhere new.",
	    "I am bothered by unsteady or fast moving visual images in movies or TV.",
	    "I don't notice when people come into the room.",
	    "I choose to shop in smaller stores because I'm overwhelmed in large stores.",
	    "I become bothered when I see lots of movement around me (for example, at a busy mall, parade, or carnival).",
	    "I limit distractions when I am working (for example, I close the door, or turn off the TV).",
	    "I dislike having my back rubbed.",
	    "I like how it feels to get my hair cut.",
	    "I avoid or wear gloves during activities that will make my hands messy.",
	    "I touch others when I'm talking (for example, I put my hand on their shoulder or shake their hands).",
	    "I am bothered by the feeling in my mouth when I wake up in the morning.",
	    "I like to go barefoot.",
	    "I'm uncomfortable wearing certain fabrics (eg. wool, silk, corduroy, tags in clothing).",
	    "I don't like particular food textures (eg. peaches with skin, applesauce, cottage cheese, chunky peanut butter).",
	    "I move away when others get too close to me.",
	    "I don't seem to notice when my face or hands are dirty.",
	    "I get scrapes or bruises but don't remember how I got them.",
	    "I avoid standing in lines or stand close to other people because I don't like to get too close to others.",
	    "I don't seem to notice when someone touches my arm or back.",
	    "I work on two or more tasks at the same time.",
	    "It takes me more time than other people to wake up in the morning.",
	    "I do things on the spur of the moment (in other words, I do things without making a plan ahead of time).",
	    "I find time to get away from my busy life and spend time by myself.",
	    "I seem slower than others when trying to follow an activity or task.",
	    "I don't get jokes as quickly as others.",
	    "I stay away from crowds.",
	    "I find activities to perform in front of others (for example, music, sports, acting, public speaking, and answering questions in class).",
	    "I find it hard to concentrate for the whole time when sitting in a long class or a meeting.",
	    "I avoid situations where unexpected things might happen (for example, going to unfamiliar places or being around people I don't know).",
	    "I hum, whistle, sing or make other noises.",
	    "I startle easily at unexpected or loud noises (eg. vacuum cleaner, dog barking, telephone ringing).",
	    "I have trouble following what other people are saying when they talk fast or about unfamiliar topics.",
	    "I leave the room when others are watching TV, or I ask them to turn it down.",
	    "I am distracted if there is a lot of noise around.",
	    "I don't notice when my name is called.",
	    "I use strategies to drown out sound (eg. close the door, cover my ears, wear ear plugs).",
	    "I stay away from noisy settings.",
	    "I like to attend events with a lot of music.",
	    "I have to ask people to repeat things.",
	    "I find it difficult to work with background noise (eg. fan, radio)."
    );

    function __construct( Assessments $oA, AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oA, $oAsmt, $kAsmt );
    }

    public function MapRaw2Score( string $item, string $raw ) : int
    /*************************************************
     Map raw -> score for basic items
     */
    {
        $score = 0;

        if( in_array($raw, ['1','2','3','4','5']) && is_numeric($item) ) {
            $score = intval($raw);
        }

        return( $score );
    }

    public function ComputeScore( string $item ) : int
    {

        $score = 0;

        // Basic scores were computed and scored by the constructor.
        // Aggregate scores are computed below and cached here.
        if( isset($this->raScores[$item]) ) { $score = $this->raScores[$item]; goto done; }

        // Look up aggregate / computed score
        switch( $item ) {
            case "Q1_total":
            case "Q2_total":
            case "Q3_total":
            case "Q4_total":
                $score = array_sum(array_intersect_key($this->raScores, array_flip($this->raRange[explode("_", $item)[0]])));
                break;
            case "total":
                $score = array_sum(array_intersect_key($this->raScores, array_flip(array_filter(array_keys($this->raScores), 'is_numeric'))));
                break;
        }

        $this->raScores[$item] = $score;    // cache for next lookup

        done:
        return( $score );
    }

    /**
     * Get the items that match the specified score
     * @param string $section - section to fetch items back
     * @param int $score - the score to match against. item must match score to be included in return
     * @param int ...$scores - aditional score to match against.
     * @return string[][]|number[][] - array of items that match the scores
     */
    public function getItems(string $section, int $score, int ...$scores){
        $range = $this->raRange[$section];
        $raResults = array();
        foreach($range as $k){
            $v = $this->ComputeScore($k);
            if($v == $score || in_array($v, $scores)){
                $raResults[] = $this->items[$k-1];
            }
        }
        return $raResults;
    }

}

class AssessmentUI_AASP extends AssessmentUIColumns
{
    function __construct( AssessmentData_AASP $oData )
    {
        parent::__construct( $oData, $this->initColumnsDef() );
    }

    private function initColumnsDef()
    {
        $def = array(
            'taste'      => [ 'label'=>"Taste /<br/>Smell Processing", 'colRange'=>"1-8"  ],
            'movement'   => [ 'label'=>"Movement Processing",          'colRange'=>"9-16" ],
            'visual'     => [ 'label'=>"Visual Processing",            'colRange'=>"17-26" ],
            'touch'      => [ 'label'=>"Touch Processing",             'colRange'=>"27-39" ],
            'activity'   => [ 'label'=>"Activity Level",               'colRange'=>"40-49" ],
            'auditory'   => [ 'label'=>"Auditory Processing",          'colRange'=>"50-60" ],
        );
        return( $def );
    }

    function DrawScoreResults() : string
    {
        $s = "<table id='results'>
<thead><tr><th colspan='8' style='text-align:center'>Results of Adolescent Sensory Profile</th></tr></thead><tbody>
 <tr><td>Sensory Quadrant</td><td>Raw Score</td><td>Much Less than Most People</td><td>Less than Most People</td><td>Similar to Most People</td><td>More than Most People</td><td>Much More than Most People</td></tr>
 <tr><td>Low Registration</td><td>{{Q1_total}}&nbsp;/&nbsp;75</td><td>15&nbsp;-&nbsp;18</td><td>19&nbsp;-&nbsp;26</td><td>27&nbsp;-&nbsp;40</td><td>41&nbsp;-&nbsp;51</td><td>52&nbsp;-&nbsp;75</td></tr>
 <tr><td>Sensory Seeking</td><td>{{Q2_total}}&nbsp;/&nbsp;75</td><td>15&nbsp;-&nbsp;27</td><td>28&nbsp;-&nbsp;41</td><td>42&nbsp;-&nbsp;58</td><td>59&nbsp;-&nbsp;65</td><td>66&nbsp;-&nbsp;75</td></tr>
 <tr><td>Sensory Sensitvity</td><td>{{Q3_total}}&nbsp;/&nbsp;75</td><td>15&nbsp;-&nbsp;19</td><td>20&nbsp;-&nbsp;25</td><td>26&nbsp;-&nbsp;40</td><td>41&nbsp;-&nbsp;48</td><td>49&nbsp;-&nbsp;75</td></tr>
 <tr><td>Sensory Avoiding</td><td>{{Q4_total}}&nbsp;/&nbsp;75</td><td>15&nbsp;-&nbsp;18</td><td>19&nbsp;-&nbsp;25</td><td>26&nbsp;-&nbsp;40</td><td>41&nbsp;-&nbsp;48</td><td>49&nbsp;-&nbsp;75</td></tr>
</tbody></table>
<script>
function colorTable(id) {
	var table = document.getElementById(id);
	document.querySelectorAll('#' + id + ' tbody tr:not(:first-child)').forEach(function(row) {
		var val = row.children[1].innerHTML.split('&nbsp;/&nbsp;')[0];
		for (var i in row.children) {
			if (i == 0) {
				continue;
			}
			var cell = row.children[i];
			if (!cell.innerHTML) return;
			var lims = cell.innerHTML.split('&nbsp;-&nbsp;');
			if (val >= Number(lims[0]) && val <= Number(lims[1])) {
				cell.style.backgroundColor = 'yellow';
				return;
			}
		}
	});
}
colorTable('results');
</script>";
        $s = preg_replace_callback("/{{(.*?)}}/", function ($match){
            return $this->oData->ComputeScore($match[1]);
        }, $s);
        $s .= $this->DrawColFormTable( $this->oData->GetForm(), false );
        return( $s );
    }
}
