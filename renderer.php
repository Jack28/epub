<?php
	/**

	*/

use Mpdf\Tag\Tr;

	// must be run within Dokuwiki
	if (!defined('DOKU_INC')) die();

	if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
	if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
	if(!defined('EPUB_DIR')) define('EPUB_DIR',realpath(dirname(__FILE__)).'/');
	require_once DOKU_INC.'inc/parser/xhtml.php';
	require_once(EPUB_DIR.'scripts/epub_utils.php');

	class renderer_plugin_epub extends Doku_Renderer_xhtml {
		private $opf_handle;
		private $oebps;
		private $current_page;
		private $allow_url_fopen;
		private $isWin;
        private $audio_link;
         private $audio_nmsp;
         private $audio_nmsp_orig;
         private $video_link;
         private $video_nmsp;
         private $video_nmsp_orig;
	     function getInfo() {
			return array(
            'author' => 'Myron Turner',
            'email'  => 'turnermm02@shaw.ca',
            'date'   => '2011-07-1',
            'name'   => 'epub',
            'desc'   => 'renderer for ebook creator',
            'url'    => 'http://www.dokuwiki.org/plugin:epub');
		}

	    function __construct() {
            $this->allow_url_fopen=ini_get ( 'allow_url_fopen' ) ;
            $this->isWin=function_exists('epub_isWindows') ? epub_isWindows() : false;
            $this->mpeg_settings('audio') ;
            $this->mpeg_settings('video') ;
		}

        function mpeg_settings($which) {
            if($which == 'audio') {
			$this->audio_link = $this->getConf('audio_fn');
                $nmsp = 'audio_nmsp';
            }
            else {
                $this->video_link = $this->getConf('video_fn');
                $nmsp = 'video_nmsp';
            }
            $nmsp = $this->getConf($nmsp);
            $nmsp = str_replace(' ','',$nmsp);
            $nmsp = str_replace(',','|',$nmsp);
            $nmsp_orig = $nmsp;
            $nmsp = str_replace(':','_',$nmsp);
            if($which == 'audio') {
                $this->audio_nmsp = $nmsp;
                $this->audio_nmsp_orig = $nmsp_orig;
                return;
            }
            $this->video_nmsp = $nmsp;
            $this->video_nmsp_orig = $nmsp_orig;
		}

		/**
			* Make available as XHTML replacement renderer
		*/
		public function canRender($format){
			if($format == 'xhtml') return true;
			return false;
		}

		function set_oebps() {
		   $this->oebps = epub_get_oebps();
		}
        function set_current_page($page) {
		   $this->current_page=$page;
		}

		function opf_handle() {
			if(!$this->opf_handle) {
				$this->opf_handle= fopen($this->oebps. 'content.opf', 'a');
			}
			return $this->opf_handle;
		}

		function is_epubid($id) {
			return is_epub_pageid($id);
		}
		/**
			* Simplified header printing with PDF bookmarks
		*/
		function header($text, $level, $pos, $returnonly = false) {
			if(!$text) return; //skip empty headlines
		    $hid = $this->_headerToLink($text, true);
			// print header
			$this->doc .= DOKU_LF."<h$level id='$hid'>";
			$this->doc .= $this->_xmlEntities($text);
			$this->doc .= "</h$level>".DOKU_LF;
		}

        function internalmedia($src, $title = null, $align = null, $width = null, $height = null, $cache = null, $linking = null, $return = false) {
            if ($linking !== 'linkonly')
                return parent::internalmedia($src, $title, $align, $width, $height, $cache, $linking, $return);

            $url = ml($src, '', true, '&', true);
            return parent::externallink($url, $title, $return);
        }

        /**
        * Wrap centered media in a div to center it
		*/
		function _media($src, $title=NULL, $align=NULL, $width=NULL, $height=NULL, $cache=NULL, $render = true) {

			$mtype = mimetype($src);
			if(!$title) $title = $src;
		    $external = false;
            $src = trim($src);
            $out = "";
            if(is_external_uri($src))
			   $external = true;

            if($external && !$this->allow_url_fopen)  {
                $link = $this->create_external_link($src);
                return $this->_formatLink($link);
            }

			$src = $this->copy_media($src, $external, $width, $height);

			if(strpos($mtype[1],'image') !== false)       {
				$out .= $this->set_image($src, $title, $width, $height, $align);
			}
            else if(strpos($mtype[1],'audio') !== false)       {
				if($this->audio_link)  $out .= '</p><div style="text-align:center">' ;
               $out .= $this->set_audio($src,$mtype,$title) ;
                if($this->audio_link) {  // set audio footnote
                 list($title,$rest) = explode('(', $title);
                     $mpfile = str_replace('Audio/',"",$src);
                         $display_name = $title;
                         $title = $mpfile;
                     $out .=  $this->_formatLink( array('class'=>'media mediafile mf_mp3','title'=>$title,'name'=>$title, 'display'=>$display_name) )  ."\n</div><p>";
			    }
			}
         else if(strpos($mtype[1],'video') !== false)       {
                     if($this->video_link)  $out .= '</p><div style="text-align:center">' ;//$out .= '<div style="text-align:center">' ;
                $out .= $this->set_video($src,$mtype,$title) ;
                    if($this->video_link) {
                          list($title,$rest) = explode('(', $title);
                         $mpfile = str_replace('Video/',"",$src);
                         $display_name = $title;
                         $title = $mpfile;
                         $out .=  $this->_formatLink( array('class'=>'media mediafile mf_mp4','title'=>$title,'name'=>$title, 'display'=>$display_name) )  ."\n</div><p>";
                    }
         }
			else {
                $out .= parent::_media($src, $title, $align, $width, $height, $cache, $render);
				//$out .= "<a href='$src'>$title</a>";
			}
			return $out;
		}

        function create_external_link($name) {
            return array(
                "target" => "",
                "style" => "",
                "pre" => "",
                "suf" => "",
                  "type"=>'ext_media',
                "more" =>  'rel="nofollow"',
                "class" => 'urlextern',
                "url" => $name,
                "name" => basename($name),
                "title" => $name
              );
        }

		/**
			* hover info makes no sense in PDFs, so drop acronyms
		*/
		function acronym($acronym) {
			$this->doc .= $this->_xmlEntities($acronym);
		}


		function is_image($link,&$type) {
			$class = $link['class'];
			if(strpos($class,'media') === false)  {
				$type=$class;
				return false;
			}

			$mime_type = mimetype($link['title']);
			if(!$mime_type[1] ) {
				list($url,$rest) = explode('?', $link['url']);
				$mime_type = mimetype($url);
                if(!$mime_type[1]) {
                    $mime_type = mimetype($rest);
                }
			}

			if(!$mime_type[1]) {
				$type = 'other';
				return false;
			}

			list($type,$ext) = explode('/', $mime_type[1] );

			if($type != 'image') {
				$type='media';
				return false;
			}

			return true;
		}
		/**
			* reformat links if needed
		*/

		function _formatLink($link){
			$class = $link['class'];
			$type = "";

			// echo 'Link: ';
			// print_r($link);

            $title = $link['title'];

            // image
			if($this->is_image($link,$type)) {
                // external
				if(preg_match('#media\s*=\s*http#',$link['url'])) {
					$name = $this->copy_media($title, true);
					if($name) {
  		  			    return $this->set_image($name) ;
					}
					else return $this->set_image('');
				}
                // internal
				else {
                    $img = $link['name'];
                    if(strpos($img,'<img') !== false) {
                        $name = $this->copy_internal_image($title, $img);
                        //$name = $this->copy_media($title);
                        if(strpos($img,'fetch.php') !== false) {
                            $img = preg_replace("#src=.*?$title\"#", "src=\"$name\"",$img);
                            if(strpos($img,'alt=""') !==false) {
                                $img = str_replace('alt=""','alt="'. $title . '"', $img);
                            }
                            return $this->clean_image_link($img);
                        }
                        elseif(strpos($link['url'],'fetch.php') !== false) {
                            if(preg_match('/src=\"(.*?)\"/',$img,$matches)) {
                                $img = '<a href="' . $matches[1] .'">' . $title . '</a>';
                                return $this->clean_image_link($img);
                            }
                        }
                        $img = $this->clean_image_link($img);

                        return $img;
                    }
                }

				$name = $this->copy_media($title);
				return $this->set_image($name);
			}
            else if($class == 'media' && strpos($link['name'],'<img') !== false) {
				$id = "";
				$name = $this->local_name($link,$id,$frag);
				// page inside
				if ($this->is_epubid($id)) {
					$name = epub_clean_name($name);
					$name .='.html';
					$link['url'] = $name;
            		if($frag) $link['url'] .="#$frag";
				}
				// print_r($link);
                $this->doc .= '<a href="'  .  $link['url']  .'" class="media" title="' . $title . '"  rel="nofollow">' . $link['name'] . '</a>';
                return;
            }
            //internal link
			if((strpos($class,'wikilink') !== false ) && $type!='media') {
				$id = "";
				$name = $this->local_name($link,$id,$frag);
				// outside
				if(!$this->is_epubid($id)) {
					//echo "... skipped\n";
					$link['class'] = 'urlextern';
					return parent::_formatLink($link);
					// $doku_base = DOKU_BASE;
					// $doku_base = trim($doku_base,'/');
					// $fnote =  DOKU_URL .  "doku.php?id=$id";
					// return $this->set_footnote($link,$fnote);
				}
               	// inside
               	$name = epub_clean_name($name);
				$name .='.html';
                if($class == 'wikilink2') {
                    $wfn =  wikiFN($id);
                    if( file_exists($wfn) ) $link['class'] =  'wikilink1';
                }

			}
			else if($type=='media') {  //internal media
				$id = "";
				$name = $this->local_name($link,$id);
				//$display = $link['display'];
                if(!empty($display)) {
                    $link['name'] = $display;
                    if(strpos($class,'mp3') !== false) {
                    	$id = preg_replace('/^(' .  $this->audio_nmsp  . ')_/', "$1:", $id);
                        $id = preg_replace('/^(' .  $this->audio_nmsp_orig  . ')_/', "$1:", $id);  //two levels deep
						$link['url'] = ml($id, '', true, '&amp;', true);
                    }
                    else if(strpos($class,'mp4') !== false) {
                        $id = preg_replace('/^(' .  $this->video_nmsp  . ')_/', "$1:", $id);
                        $id = preg_replace('/^(' .  $this->video_nmsp_orig  . ')_/', "$1:", $id);
						$link['url'] = ml($id, '', true, '&amp;', true);
                    }
                }
				return parent::_formatLink($link);
				// $note_url =  DOKU_URL .  "lib/exe/fetch.php?media=" . $id;
                // $link['class'] = 'wikilink1';
				// $out = $this->set_footnote($link,$note_url);
				// $out=preg_replace('/<a\s+href=\'\'>(.*?)<\/a>(?=<a)/',"$1",$out);		//remove link markup from link name
				// return $out;
			}
			elseif($class != 'media') {   //  or urlextern	or samba share or . . .
				return parent::_formatLink($link);
                // $out = $this->set_footnote($link,trim($link['url']));		// creates an entry in output for the link  with a live footnote to the link
                // if(isset($link['type']) && $link['type'] == 'ext_media') {
                //     $this->doc .= $out;
                // }
                // else return $out;
			}

			if(!$name) return;
			$link['url'] = $name;
            if($frag) $link['url'] .="#$frag";
			// echo 'Prepared link: ';
			// print_r($link);
			return parent::_formatLink($link);
		}

         function clean_image_link($link) {
                $link = str_replace('Images/Images',"Images",$link);
                $link = preg_replace('#[\.\/]*Images#', "../Images", $link );
                return $link;
         }
        //  function set_footnote($link, $note_url="") {
 		// 			$out = $link['name'];
		// 			$fn_id = epub_fn();
		// 			$link['name'] = "[$fn_id]";
        //             if(preg_match("/media\s*=\s*(http.*)/", $link['url'],$matches)) {   //format external  urls
        //                 $note_url = urldecode($matches[1]);
        //             }
		// 			$link['url'] = 'footnotes.html#' .$this->current_page;
		// 			$link['class'] = 'wikilink1';
        //             $id = 'backto_' . $fn_id;
		// 			$hash_link="<a id='$id' name='$id'></a>";
		// 			$out .= $hash_link . parent::_formatLink($link); // . '</a>';
		// 			epub_write_footnote($fn_id,$this->current_page,$note_url);
		// 			return $out;

        //  }

        function smiley($smiley) {
            static $smileys;
             if(!$smileys) $smileys = getSmileys();

             if ( array_key_exists($smiley, $this->smileys) ) {
                 $spath = DOKU_INC . 'lib/images/smileys/'.$smileys[$smiley];
                 $name = $this->copy_media($spath,true);
                 $this->doc .= $this->_media($name);
             }
         }

        function local_name($link, &$id="", &$frag ="") {
			$url = $link['url'];
            $base_name= basename($url);
			$title = $link['title'];
			$title = $title ?  ltrim($title,':') : ($conf['useheading'] ?  p_get_first_heading($url) : "");
            list($starturl,$frag) = explode('#',$url);
            if ($title) {
                $name = $title;
            }
            elseif($base_name) {
                list($name,$rest) = explode('?',$base_name);
            }

            if($name) {
				$more = $link['more'];
				if ($more &&  preg_match('/data-wiki-id="([^"]*)"/', $more, $matches)) // page internallink
					$id = $matches[1];
				else
					$id = $name;
				$id = ltrim($id, ':');
				$name = epub_clean_name(str_replace(':', '_', $id));
				//echo '... '.($this->is_epubid($id) ? 'local' : 'external').', '.$name.' = '.$id."\n";
				return $name;
            }
			//echo "... no name\n";
            return false;
        }

        function copy_internal_image($media, $img) {
            $width = parse_tag_attribute_int($img, 'width');
            $height = parse_tag_attribute_int($img, 'height');
            return $this->copy_media($media, false, $width, $height);
        }

		function copy_media($media, $external=false, $width=NULL, $height=NULL) {
			$resize = false;

            // echo 'copy_media '.$media."\n";
			$name =  epub_clean_name(str_replace(':','_',basename($media)));
            // echo ' name '.$name."\n";

			$mime_type = mimetype($name);
			list($type,$ext) = explode('/', $mime_type[1] );
			if($type !== 'image' && $type != 'audio' && $type != 'video')  return;
			if($external) {
                if(!$this->allow_url_fopen) return;
                $tmp =  str_replace('https://',"",$media);
                $tmp =  str_replace('http://',"",$media);
                $tmp =  str_replace('www.',"",$tmp);
				if($this->isWin) {
				    $tmp =  preg_replace('/^[A-Z]:/',"",$tmp);
				}
                $tmp=ltrim($tmp,'/\/');

                $elems=explode('/',$tmp);
				if($this->isWin) {
				    $elems=explode('\\',$tmp);
				}
                if(!empty($elems) && $elems[0]) {
                    $elems[0] = preg_replace('#/\W#','_',$elems[0]);
                    $name = $elems[0]. "_" . $name;
                }
            }
          if($type == 'audio') {
               $name = "Audio/$name";
           }
          else if($type == 'video') {
               $name = "Video/$name";
           }
           else if(!preg_match("/^Images/", $name)) {
                $name = "Images/$name";
                // honor image resizing to preserve space
                if (!$external &&
                    (
                        $width ||
                        $height
                    )) {
                    $file = $this->oebps . $name;
			        if (!file_exists($file)) {
                        $resize = true;

                        //$external = true;
                        // $more = [];
                        if ($width) {
                            $name = prepend_before_file_extension($name, '.w' . $width);
                            //  $more['w'] = $width;
                        }
                        if ($height) {
                            $name = prepend_before_file_extension($name, '.h' . $height);
                            //  $more['h'] = $height;
                        }
                        $media = media_resize_image(mediaFN($media), $ext, $width, $height);
                        // echo ' resized media '.$media."\n";
                        // $media2 = $media;
                        // $media = ml($media, $more, true, '&', true);
                        // echo ' ml '.$media."\n";
                    }
                }
            }

		    $file = $this->oebps . $name;

			if(file_exists($file))
                return $name;

			if(!$external && !$resize) {
				$media = mediaFN($media);
                // echo ' mediaFN '.$media."\n";
			}

            // echo ' file '.$file."\n";
			if(copy ($media ,  $file)) {
				epub_write_item($name,$mime_type[1]) ;
				return $name;
			}
            else if(!$this->isWin && epub_save_image($media ,  $file)) {
                epub_write_item($name,$mime_type[1]) ;
                return $name;
            }
			return false;
		}

		function set_image($img, $title = null, $width=null, $height=null, $align=null) {
			$w="";
			$h="";
			$result="";
			if($width)   $w= ' width="' . $width . '"';
			if($height)   $h= ' height="' .$height . '"';
            $img = $this->clean_image_link($img);
            $class='media';
            if($align) {
                $class .= $align;
            }
			if ($title)
				$title = ' title="'.$title.'"';
			$center = $align === 'center';
			if ($center)
				$result = '<div>';
			$result .= '<img src="' . $img . '"' .  $h . $w . $title . ' alt="'. $img . '" class="' .  $class . '" />';
			if ($center)
				$result .= '</div>';
			return $result;
		}

        function set_audio($src,$mtype,$title) {
            $src = "../$src";
            $type = $mtype[1];
            $out = '<audio class="mediacenter" controls="controls">' . "\n";
            $out .= "<source src= '$src' type='$type' />" .
            "\n<a href='$src' title='$title'>$title</a></audio>\n";
            return $out;
        }

        function set_video($src,$mtype,$title) {
            $src = "../$src";
            $type = $mtype[1];
            $out = '<video class="mediacenter" controls="controls">' . "\n";
            $out .= "<source src= '$src' type='$type' />" .
            "\n<a href='$src' title='$title'>$title</a></video>\n";
            return $out;
        }

        function plugin($name,$data,$state = '', $match = '') {

		    if($name !='mathpublish' && $name !='ditaa' && $name !='graphviz') return parent::plugin($name,$data);

		    $mode ='xhtml';
		    $renderer =p_get_renderer($mode );
            $plugin =& plugin_load('syntax',$name);
            if($plugin != null) {
                $plugin->render($mode,$renderer,$data);

		        if($name == 'ditaa') {
			        epub_check_for_ditaa($renderer->doc,$this);
                }
				else if($name =='mathpublish') {

					epub_check_for_math($renderer->doc,$this);
				}
				else if($name =='graphviz') {
					epub_check_for_graphviz($renderer->doc,$this,$data,$plugin);
				}
				$this->doc .= $renderer->doc;
		    }
        }

		/**
			* no obfuscation for email addresses
		*/
		function emaillink($address, $name = NULL,$returnonly = false) {
			global $conf;
			$old = $conf['mailguard'];
			$conf['mailguard'] = 'none';
			parent::emaillink($address, $name);
			$conf['mailguard'] = $old;
		}

		function write($what,$handle) {
			fwrite($handle,"$what\n");
			fflush($handle);
		}
	}

