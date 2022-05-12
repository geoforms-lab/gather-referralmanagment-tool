el.addClass("inline security");
el.setAttribute("data-col","security");


var users=item.getUsers();
    
var description=[];


if(users.length>0){
    valueEl.appendChild(new Element('span', {'html':users.length, 'class':'team-members'}));
    description.push(users.length==1?'There is 1 team member':'There are '+users.length+' team members');
}

console.log('security list');



var team=ProjectTeam.CurrentTeam();
/*
var viewers=team.getUsers().filter(function(u){
    return u.isTeamManager() &&
        users.map(function(u){return u.getId()}).indexOf(u.getId())==-1 &&
            (item.getCommunitiesInvolved().indexOf(u.getCommunity())!=-1||item.getProjectCommunity()==u.getCommunity());
});

if(viewers.length>0){
    valueEl.appendChild(new Element('span', {'html':viewers.length, 'class':'managers'}));
    description.push(viewers.length==1?'There is 1 manager':'There are '+viewers.length+' managers');
}
*/
viewers=0;

var communities=item.getCommunitiesInvolved();
if(communities.length>0){
    valueEl.appendChild(new Element('span', {'html':communities.length, 'class':'communities'}));
    description.push(communities.length==1?'There is 1 community':'There are '+communities.length+' communities');
}

var links=item.getShareLinks();
if(links.length>0){
    valueEl.appendChild(new Element('span', {'html':links.length, 'class':'share-links'}));
    description.push(links.length==1?'There is 1 share link':'There are '+links.length+' share links');
}





if(users.length>0||viewers.length>0||links.length>0||communities.length){
     new UIPopover(valueEl, {
        description:'<h3>Item Access:</h3>'+description.map(function(d, i){
            if(i>0){
                d=d.replace('There is ','').replace('There are ','');
            }
                
            if(i>0&&i==description.length-1){
               d='and '+d;
            }
            
            return d;
        }).join(', '),
        anchor:UIPopover.AnchorAuto()
    });
}



    
el.addEvent('click', function(e){
   e.stop();
   UIInteraction.navigateToProjectSection(item ,"Security");
    
});