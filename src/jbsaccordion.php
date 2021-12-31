<?php
// (c) 2021 by Achim Raphael

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class PlgContentjbsaccordion extends JPlugin
{
	protected $convertRLsliders = 0;

	protected $GROUPTAG    = "jbsgroup";
	protected $SUBGROUPTAG = "jbssubgroup";
	protected $CARDTAG     = "jbscard";

	public function onContentPrepare( $context, &$article, &$params, $page=0 )
	{
		$load_bootstrap = (int)$this->params->get("load_bootstrap", "1");

		if ( $load_bootstrap == 1 ) {
			\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.collapse', '.selector', []);
		}

		$txt = $article->text;

		// Converting Regular Labs Sliders to jbsSliders (Very limited)
		if ( $this->convertRLsliders )  $txt = static::convertRLS( $txt );

		if ( !strpos( $txt, $this->CARDTAG ) && !strpos( $txt, $this->SUBGROUPTAG ) && !strpos( $txt, $this->GROUPTAG ) ) return;

		// strip surrounding <p>...</p> tags, possibly added by editor:
		$txt = static::stripEditorTags( $txt, $this->GROUPTAG );
		$txt = static::stripEditorTags( $txt, $this->SUBGROUPTAG );
		$txt = static::stripEditorTags( $txt, $this->CARDTAG );

		// replace all jbsaccordion tags with bootstrap accordion code:
		$txt = static::insertGroups( $txt, $article->id );

		// final cleanup: delete <p> and </p> tags in between accordion-items
		// no problem with single collapse items, because they are surrounded by divs (see below)
                $tags = preg_match_all( "/<[\/]*p>(&nbsp;|\s)*?(<div class\=\"accordion-item\">)/is", $txt, $matches );
                for ( $i=0; $i<$tags; $i++) { $txt = str_replace( $matches[0][$i], $matches[2][$i], $txt ); }

		$article->text = $txt;
	}

	protected function insertGroups( $txt, $anum )
	{
		// replacing {GROUPTAG}Content{/GROUPTAG} with an accordion

		$found = preg_match_all( "/{" . $this->GROUPTAG . ".*?}(.*?){\/" . $this->GROUPTAG . "}/is", $txt, $matches );

                for ( $i=0; $i<$found; $i++ ) {
                        $wholematch = $matches[0][$i];
                        $groupcontent = $matches[1][$i];

                        $groupid = $this->GROUPTAG . "-" . $anum . "-" . ($i+1) . "-0";
                        $groupopentag = "<div id=\"$groupid\" class=\"accordion\">";
                        $groupclosetag = "</div>";

			// parsing for subgroups and cards:
                        $newcontent = static::insertSubGroups( $groupcontent, $anum, $i+1, $groupid );

                        $p = strpos( $txt, $wholematch );
                        $txt = substr_replace( $txt, "$groupopentag\n$newcontent\n$groupclosetag\n", $p, strlen( $wholematch ) );
                }

		// Handeling all remaining single collapsibles in the article (CARDTAG Header}Content{/CARDTAG}
		$txt = static::insertCards( $txt, $anum, 0, 0, "" );

		return $txt;
	}


        protected function insertSubGroups( $txt, $anum, $gnum, $pid )
        {
		// replacing {SUBGROUPTAG Header}Content{/SUBGROUPTAG} with an 2. level accordion

                $found = preg_match_all( "/{" . $this->SUBGROUPTAG . "(.*?)}(.*?){\/" . $this->SUBGROUPTAG . "}/is", $txt, $matches );

                for ( $i=0; $i<$found; $i++ ) {
                        $wholematch = $matches[0][$i];
                        $groupcontent = $matches[2][$i];
			$groupheader = $matches[1][$i];

                        $groupid = $this->SUBGROUPTAG . "-$anum-$gnum-" . ($i+1);
                        $groupopentag = "<div id=\"$groupid\" class=\"accordion\">";
                        $groupclosetag = "</div>";
			// subgroup must be inside a card. (Second level accordion resides in an accordion-item) - Therefor:
                        $groupopentag = "{" . $this->CARDTAG . " $groupheader}" . $groupopentag;
                        $groupclosetag = $groupclosetag . "{/" . $this->CARDTAG . "}";

                        $newcontent = static::insertCards( $groupcontent, $anum, $gnum, $i+1, $groupid );

                        $p = strpos( $txt, $wholematch );
                        $txt = substr_replace( $txt, "$groupopentag\n$newcontent\n$groupclosetag\n", $p, strlen( $wholematch ) );
                }

		// Handeling all remaining single collapsibles inside the accordion (CARDTAG Header}Content{/CARDTAG}
                $txt = static::insertCards( $txt, $anum, $gnum, 0, $pid );

                return $txt;
        }


	protected function insertCards( $txt, $anum, $gnum, $sgnum, $pid )
	{
		// replacing {CARDTAG Header}Content{/CARDTAG} with the bootstrap accortdion-item code

		// Distinguish between single collapsibles (no parent) and accordion-members
		$parenttag = $pid == "" ? "" : " data-bs-parent=\"#$pid\"";

		$found = preg_match_all( "/{" . $this->CARDTAG . "(.*?)}(.*?){\/" . $this->CARDTAG . "}/is", $txt, $matches );

		for ( $i=0; $i<$found; $i++ ) {
			$wholestuff = $matches[0][$i];
			$cardtitle = $matches[1][$i];
			$cardcontent = $matches[2][$i];

			// create unique identifiers:
			$cnum = $i+1;
			$headid = $this->CARDTAG . "Head-$anum-$gnum-$sgnum-$cnum";
			$bodyid = $this->CARDTAG . "Body-$anum-$gnum-$sgnum-$cnum";

// ------- bootstrap accordion-item code --------------------
			$cardcode = <<<EOCC
  <div class="accordion-item">
    <div class="accordion-header" id="$headid">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#$bodyid" aria-expanded="false" aria-controls="$bodyid">
$cardtitle
      </button>
    </div>
    <div id="$bodyid" class="accordion-collapse collapse" aria-labelledby="$headid" $parenttag>
      <div class="accordion-body">
$cardcontent
      </div>
    </div>
  </div>

EOCC;
// -------------------------------------------------

	                // Singele card (accordion-item) must nevertheless be enclosed by divs
	                // Otherwise BS doesn't see it as an accordion and the BS-css messes up the layout a bit (surrounding borders)
                	if ( $pid == "" )  $cardcode = "<div>$cardcode</div>";

			$p = strpos( $txt, $wholestuff );
			$txt = substr_replace( $txt, $cardcode, $p, strlen( $wholestuff ) );
		}
		return $txt;
	}


        protected function stripEditorTags( $txt, $identifier )
        {
		// strip <p>...</p> from jbs-tags (most editors add these automatically)

		$tags = preg_match_all( "/<p\s*.*?>((<br \/>)*?(&nbsp;| )*?{[\/]*" . $identifier . ".*?}(&nbsp;| )*?(<br \/>)*?)<\/p>/i", $txt, $matches );
		for ( $i=0; $i<$tags; $i++)  { $txt = str_replace( $matches[0][$i], $matches[1][$i], $txt ); }

		return $txt;
	}


        protected function convertRLS( $txt )
        {
		// Converting Regular Labs Sliders to jbsSliders
		// Very limited. Ignores Options in Header {slider key="val"} and does not cover nested sliders.

		$ct = $this->CARDTAG;
		$gt = $this->GROUPTAG;

                $p = strpos( $txt, "{slider" );
                if ($p !== false) $txt = substr_replace( $txt, "{".$gt."}{".$ct, $p, strlen( "{slider" ) );
		$txt = str_replace( "{slider" , "{/".$ct."}{".$ct , $txt );
                $txt = str_replace( "{/sliders" , "{/".$ct."}{/".$gt , $txt );

		return $txt;
	}

}
?>
