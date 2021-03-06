<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Search_model extends CI_Model { 
    public function quickSearch($srno, $key, $filter) {
        $response = array();
        $query = '';

        if($filter == 'search_r') {
            $query = $this->db->query("SELECT recipes.srno, recipes.uid, recipes.title, recipes.cover_imagepath,ratings.rating, CONCAT(useraccounts.fname, ' ', useraccounts.lname) as name FROM recipes, useraccounts,ratings WHERE (recipes.title LIKE '%" . $key . "%' OR recipes.ingredients LIKE '%" . $key . "%') AND recipes.uid=useraccounts.srno AND recipes.srno = ratings.rid LIMIT 15");        
        } else if($filter == 'search_p') {
            $query = $this->db->query("SELECT useraccounts.username, CONCAT(useraccounts.fname, ' ', useraccounts.lname) as name, userprofile.profile_imagepath FROM useraccounts, userprofile WHERE (CONCAT(useraccounts.fname, ' ', useraccounts.lname) LIKE '%" . $key . "%' OR useraccounts.username LIKE '%" . $key . "%') AND useraccounts.srno = userprofile.uid LIMIT 15");        
        } else if($filter == 'search_t') {
            $query = $this->db->query("SELECT tags.name FROM tags where tags.name LIKE '%" . $key . "%' LIMIT 15");
        }
        
        if($query->num_rows() > 0) { 
            return $query->result_array();            
        }
        return false;
    }

    public function bubble_sort($arr) {
        $size = count($arr)-1;
        for ($i=0; $i<$size; $i++) {
            for ($j=0; $j<$size-$i; $j++) {
                $k = $j+1;
                if ($arr[$k]['cosine'] > $arr[$j]['cosine']) {
                    // Swap elements at indices: $j, $k
                    list($arr[$j], $arr[$k]) = array($arr[$k], $arr[$j]);
                }
            }
        }
        return $arr;
    }

    // For ALL to exist
    public function in_array_all($needles, $haystack) {
        return !array_diff($needles, $haystack);
    }

    // For ANY to exist
    public function in_array_any($needles, $haystack) {
      return !!array_intersect($needles, $haystack);
    }

    public function i_search($keyword, $user_id, $ingredients) {
        $post_ing_array = explode(',', $ingredients);

        if($keyword == '') {
            $get_ingredients_query = "select distinct recipes.*, cuisines.name as cname, userprofile.profile_imagepath, useraccounts.username, concat(useraccounts.fname, ' ', useraccounts.lname) as fullname, ratings.rating from recipes, cuisines, userprofile, useraccounts, ratings where recipes.uid = userprofile.uid AND userprofile.uid = useraccounts.srno and recipes.cid = cuisines.srno and recipes.srno = ratings.rid";
        } else {
            $get_ingredients_query = "select distinct recipes.*, cuisines.name as cname, userprofile.profile_imagepath, useraccounts.username, concat(useraccounts.fname, ' ', useraccounts.lname) as fullname, ratings.rating from recipes, cuisines, userprofile, useraccounts, ratings where recipes.uid = userprofile.uid AND userprofile.uid = useraccounts.srno and recipes.title LIKE '%" . $keyword . "%' and recipes.cid = cuisines.srno and recipes.srno = ratings.rid";
        }

        $query = $this->db->query($get_ingredients_query);
        if($query->num_rows() > 0) {
            $list_of_ingredients = $query->result_array();
            $temp = array();
            $response = array();

            for($i=0;$i<count($list_of_ingredients);$i++) {
                $exp = explode(',', $list_of_ingredients[$i]['ingredients']);             
                $list_of_ingredients[$i]['temp_ing'] = $exp;
            }            
            
            for($i = 0; $i < count($list_of_ingredients); $i++) {
                for($j = 0; $j < count($post_ing_array); $j++) {
                    if(preg_grep("/" . $post_ing_array[$j] . "/", $list_of_ingredients[$i]['temp_ing'])) {
                        array_push($response, array(
                            "recipe_id"=> $list_of_ingredients[$i]['srno'],
                            "title"=> $list_of_ingredients[$i]['title'],                        
                            "ingredients_html"=> $list_of_ingredients[$i]['ingredients_html'],               
                            "ingredients"=> $list_of_ingredients[$i]['ingredients'],               
                            "cover_imagepath"=> $list_of_ingredients[$i]['cover_imagepath'],                       
                            "description"=> $list_of_ingredients[$i]['description'],                        
                            "prep_time"=> $list_of_ingredients[$i]['prep_time'],                        
                            "cooking_time"=> $list_of_ingredients[$i]['cooking_time'],                        
                            "spicy"=> $list_of_ingredients[$i]['spicy'],                        
                            "food_group"=> $list_of_ingredients[$i]['food_group'],                        
                            "calorie_intake"=> $list_of_ingredients[$i]['calorie_intake'],                        
                            "servings"=> $list_of_ingredients[$i]['servings'],                        
                            "cid"=> $list_of_ingredients[$i]['cid'],                        
                            "cname"=> $list_of_ingredients[$i]['cname'],                        
                            "uid"=> $list_of_ingredients[$i]['uid'],                        
                            "username"=> $list_of_ingredients[$i]['username'],                        
                            "fullname"=> $list_of_ingredients[$i]['fullname'],                        
                            "profile_imagepath"=> $list_of_ingredients[$i]['profile_imagepath'],                   
                            "rating"=> $list_of_ingredients[$i]['rating'],                   
                            "timestamp"=> $list_of_ingredients[$i]['timestamp'],                        
                            "time_elapsed"=> time_elapsed($list_of_ingredients[$i]['timestamp']),                 
                        ));
                    }
                }
            }
            
            for($i = 0; $i < count($response); $i++) { 
                if($response[$i]['cover_imagepath'] != '') 
                    $response[$i]['cover_imagepath'] = 'userdata/' . (int)$response[$i]['uid'] . '/' . $response[$i]['cover_imagepath'];
                else 
                    $response[$i]['cover_imagepath'] = '';                

                $query_favourites = $this->db->query("select * from favourites where rid = " . (int)$response[$i]['recipe_id'] . " and uid = " . $user_id . "");                
                if($query_favourites->num_rows() > 0) {
                    $response[$i]['addedToFavourites'] = true;
                } else {
                    $response[$i]['addedToFavourites'] = false;
                }        

                $query_gallery = $this->db->query("select gallery.path from gallery where rid = " . (int)$response[$i]['recipe_id'] . " and type = 2");
                if($query_gallery->num_rows() > 0){
                    $response[$i]['video'] = 'userdata/' . (int)$response[$i]['uid'] . '/' . $query_gallery->row_array()['path'];
                } else {
                    $response[$i]['video'] = "";
                }                
                
                $query_upvotes = $this->db->query("SELECT * FROM upvotes WHERE rid = " . $response[$i]['recipe_id']);
                $response[$i]['upvotes'] = $query_upvotes->num_rows();
                $query_replies = $this->db->query("SELECT * FROM reply WHERE rid = " . $response[$i]['recipe_id']);
                $response[$i]['replies'] = $query_replies->num_rows();
                $query_views = $this->db->query("SELECT * FROM views WHERE rid = " . $response[$i]['recipe_id']);
                $response[$i]['views'] = $query_views->num_rows();

                $query_isupvoted = $this->db->query("SELECT * FROM upvotes WHERE rid = " . $response[$i]['recipe_id'] . " and uid = " . $user_id . "");
                if($query_isupvoted->num_rows() > 0) {
                    $response[$i]['isUpvoted'] = true;
                } else {
                    $response[$i]['isUpvoted'] = false;
                } 
            }            
            return $response;
        }                
        return false;        
    }
 
    /*public function ingredientSearch($keyword, $userid, $ingredients, $exclude) {        
        /* $query = $this->db->query("SELECT distinct recipes.srno, recipes.title, recipes.ingredients from recipes, userprofile where title LIKE '%" . $keyword . "%'");
        $result = $query->result_array();

        $articles = array();
        
        for($i = 0; $i < count($result); $i++) {
            $articles[$i] = explode(',',$result[$i]['ingredients']);
            $articles[$i]['title'] = $result[$i]['title'];
        }
        
        $similarity = new Similarity();
        $tags = $similarity::tags_to_point($articles);
        $target = explode(',', $ingredients);
        $compare = array_fill_keys($target, 1) + $tags;

        $resp = array();        
        foreach($articles as $article) {
            $ak = array_fill_keys($article, 1) + $tags;              
            array_push($resp, array(
                'title'=>$article['title'],
                'ingredients'=>implode(',', $article),
                'cosine'=>$similarity::cosine($compare, $ak)
            ));            
        }          
        $resp = $this->bubble_sort($resp);
        header('Content-Type: application/json');
        die(json_encode($resp));         */
        
        /*$ing = explode(',', $ingredients);                        
        $query2 = '';
        $result2 = '';
        $count = count($ing);

        if($count == 1) {
            if($keyword == '') {
                if($exclude == '') {
                    $query2 = $this->db->query("select recipes.srno, recipes.title, recipes.ingredients from recipes where match(ingredients) against('" . $ing[0] . "' in natural language mode)");
                } else {
                    $query2 = $this->db->query("select recipes.srno, recipes.title, recipes.ingredients from recipes where FIND_IN_SET('" . $exclude . "', ingredients) = 0 AND match(ingredients) against('" . $ing[0] . "' in natural language mode)");                    
                }
            } else {
                if($exclude == '') {
                    $query2 = $this->db->query("select recipes.srno, recipes.title, recipes.ingredients from recipes where recipes.title LIKE '%" . $keyword . "%' AND match(ingredients) against('" . $ing[0] . "' in natural language mode)");
                } else {
                    $query2 = $this->db->query("select recipes.srno, recipes.title, recipes.ingredients from recipes where recipes.title LIKE '%" . $keyword . "%' AND FIND_IN_SET('" . $exclude . "', ingredients) = 0 AND match(ingredients) against('" . $ing[0] . "' in natural language mode)");                    
                }
            }                        
        } else if($count == 2) {
            if($keyword == '') {
                if($exclude == '') {
                    $query2 = $this->db->query("select recipes.srno, recipes.title, recipes.ingredients from recipes where match(ingredients) against('" . $ing[0] . "' in natural language mode) OR match(ingredients) against('" . $ing[1] . "' in natural language mode))");
                } else {                    
                    $query2 = $this->db->query("select recipes.srno, recipes.title, recipes.ingredients from recipes where FIND_IN_SET('" . $exclude . "', ingredients) = 0 AND match(ingredients) against('" . $ing[0] . "' in natural language mode) OR match(ingredients) against('" . $ing[1] . "' in natural language mode)");
                }
            } else {
                if($exclude == '') {
                    $query2 = $this->db->query("select recipes.srno, recipes.title, recipes.ingredients from recipes where recipes.title LIKE '%" . $keyword . "%' AND match(ingredients) against('" . $ing[0] . "' in natural language mode) OR match(ingredients) against('" . $ing[1] . "' in natural language mode)");
                } else {
                    $query2 = $this->db->query("select recipes.srno, recipes.title, recipes.ingredients from recipes where recipes.title LIKE '%" . $keyword . "%' AND FIND_IN_SET('" . $exclude . "', ingredients) = 0 AND match(ingredients) against('" . $ing[0] . "' in natural language mode) OR match(ingredients) against('" . $ing[1] . "' in natural language mode)");
                }
            }                        
        } else if($count == 3) {
            if($keyword == '') {
                if($exclude == '') {
                    $query2 = $this->db->query("select recipes.srno, recipes.title, recipes.ingredients from recipes where match(ingredients) against('" . $ing[0] . "' in natural language mode) OR match(ingredients) against('" . $ing[1] . "' in natural language mode) OR match(ingredients) against('" . $ing[2] . "' in natural language mode)");
                } else {
                    $query2 = $this->db->query("select recipes.srno, recipes.title, recipes.ingredients from recipes where FIND_IN_SET('" . $exclude . "', ingredients) = 0 AND match(ingredients) against('" . $ing[0] . "' in natural language mode) OR match(ingredients) against('" . $ing[1] . "' in natural language mode) OR match(ingredients) against('" . $ing[2] . "' in natural language mode)");
                }
            } else {
                if($exclude == '') {
                    $query2 = $this->db->query("select recipes.srno, recipes.title, recipes.ingredients from recipes where recipes.title LIKE '%" . $keyword . "%' AND match(ingredients) against('" . $ing[0] . "' in natural language mode) OR match(ingredients) against('" . $ing[1] . "' in natural language mode) OR match(ingredients) against('" . $ing[2] . "' in natural language mode)");
                } else {
                    $query2 = $this->db->query("select recipes.srno, recipes.title, recipes.ingredients from recipes where recipes.title LIKE '%" . $keyword . "%' AND FIND_IN_SET('" . $exclude . "', ingredients) = 0 AND match(ingredients) against('" . $ing[0] . "' in natural language mode) OR match(ingredients) against('" . $ing[1] . "' in natural language mode) OR match(ingredients) against('" . $ing[2] . "' in natural language mode)");
                }
            }                        
        }
        $result2 = $query2->result_array();
        header('Content-Type: application/json');
        die(json_encode($result2));
}*/

    public function g_search($userid, $filters) {
        $userid = (int)$userid;
        
        $query = "SELECT distinct recipes.srno as recipe_id, recipes.title, recipes.description, recipes.cover_imagepath, recipes.prep_time, recipes.cooking_time, recipes.servings, recipes.calorie_intake, recipes.spicy, recipes.food_group, recipes.cid, recipes.uid, recipes.timestamp, useraccounts.username, concat(useraccounts.fname, ' ', useraccounts.lname) as fullname, userprofile.profile_imagepath, cuisines.name as cname, ratings.rating FROM recipes, useraccounts, userprofile, cuisines, ratings, weightage WHERE recipes.title LIKE '%" . $filters['key'] . "%' AND";
        $conditions = array();

        if(!empty($filters['spicy'])) {
            $conditions[] = "recipes.spicy <= " . (int)$filters['spicy'];
        }
        if(!empty($filters['food_group'])) {
            $conditions[] = "recipes.food_group = " . (int)$filters['food_group'];
        }        
        if(!empty($filters['cid'])) {
            $conditions[] = "recipes.cid=" . (int)$filters['cid'];            
        }
        if(!empty($filters['calorie_intake'])) {
            $conditions[] = "recipes.calorie_intake <= " . (int)$filters['calorie_intake'];            
        }
        if(!empty($filters['cooking_time'])) {
            // $conditions[] = "substring_index(cooking_time, ' ', 1)=" . (int)$filters['cooking_time'];           
            $conditions[] = "cast(recipes.cooking_time as signed) <= cast('" . $filters['cooking_time'] . "' as signed)";
        }

        $sql = $query;
        if(count($conditions) > 0) {
            $sql .= " " . implode(' AND ', $conditions);
            $sql .= " AND recipes.uid = userprofile.uid AND userprofile.uid = useraccounts.srno AND recipes.cid = cuisines.srno AND recipes.srno = ratings.rid";
            // if($filters['sort_by'] == 'none') {
            //     $sql .= " AND recipes.uid = userprofile.uid AND userprofile.uid = useraccounts.srno AND recipes.cid = cuisines.srno AND recipes.srno = ratings.rid";    
            // } else if($filters['sort_by'] == 'featured') {
            //     $sql .= " AND recipes.uid = userprofile.uid AND userprofile.uid = useraccounts.srno AND recipes.cid = cuisines.srno AND recipes.srno = ratings.rid AND recipes.srno = weightage.rid order by weight DESC";
            // } else if($filters['sort_by'] == 'ratings_ltoh') {
            //     $sql .= " AND recipes.uid = userprofile.uid AND userprofile.uid = useraccounts.srno AND recipes.cid = cuisines.srno AND recipes.srno = ratings.rid order by rating ASC";
            // } else if($filters['sort_by'] == 'ratings_htol') {
            //     $sql .= " AND recipes.uid = userprofile.uid AND userprofile.uid = useraccounts.srno AND recipes.cid = cuisines.srno AND recipes.srno = ratings.rid order by rating DESC";
            // }            
        } else {
            $sql .= " recipes.uid = userprofile.uid AND userprofile.uid = useraccounts.srno AND recipes.cid = cuisines.srno AND recipes.srno = ratings.rid";
        //     if($filters['sort_by'] == 'none') {
        //         $sql .= " recipes.uid = userprofile.uid AND userprofile.uid = useraccounts.srno AND recipes.cid = cuisines.srno AND recipes.srno = ratings.rid";    
        //     } else if($filters['sort_by'] == 'featured') {
        //         $sql .= " recipes.uid = userprofile.uid AND userprofile.uid = useraccounts.srno AND recipes.cid = cuisines.srno AND recipes.srno = ratings.rid AND recipes.srno = weightage.rid order by weight DESC";
        //     } else if($filters['sort_by'] == 'ratings_ltoh') {
        //         $sql .= " recipes.uid = userprofile.uid AND userprofile.uid = useraccounts.srno AND recipes.cid = cuisines.srno AND recipes.srno = ratings.rid order by rating ASC";
        //     } else if($filters['sort_by'] == 'ratings_htol') {
        //         $sql .= " recipes.uid = userprofile.uid AND userprofile.uid = useraccounts.srno AND recipes.cid = cuisines.srno AND recipes.srno = ratings.rid order by rating DESC";
        //     }               
        }
        
        $exe_query = $this->db->query($sql);             
        if($exe_query->num_rows() > 0) { 
            $result = $exe_query->result_array();   
            for($i=0;$i<count($result);$i++) {
                if($result[$i]['cover_imagepath'] != '') 
                    $result[$i]['cover_imagepath'] = 'userdata/' . (int)$result[$i]['uid'] . '/' . $result[$i]['cover_imagepath'];
                else 
                    $result[$i]['cover_imagepath'] = '';                

                $query_favourites = $this->db->query("select * from favourites where rid = " . (int)$result[$i]['recipe_id'] . " and uid = " . $userid . "");                
                if($query_favourites->num_rows() > 0) {
                    $result[$i]['addedToFavourites'] = true;
                } else {
                    $result[$i]['addedToFavourites'] = false;
                }        
                
                $query_upvotes = $this->db->query("SELECT * FROM upvotes WHERE rid = " . $result[$i]['recipe_id']);
                $result[$i]['upvotes'] = $query_upvotes->num_rows();
                $query_replies = $this->db->query("SELECT * FROM reply WHERE rid = " . $result[$i]['recipe_id']);
                $result[$i]['replies'] = $query_replies->num_rows();
                $query_views = $this->db->query("SELECT * FROM views WHERE rid = " . $result[$i]['recipe_id']);
                $result[$i]['views'] = $query_views->num_rows();

                $query_isupvoted = $this->db->query("SELECT * FROM upvotes WHERE rid = " . $result[$i]['recipe_id'] . " and uid = " . $userid . "");
                if($query_isupvoted->num_rows() > 0) {
                    $result[$i]['isUpvoted'] = true;
                } else {
                    $result[$i]['isUpvoted'] = false;
                }        
            }
            return $result;            
        } 
        return false;
    }
}