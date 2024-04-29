const messages=document.getElementById('message');
const nameInput = document.getElementById('nom');
const rafraichir = document.getElementById('rafraichir');
simple_fetch('http://localhost/~12203019/chat.php?action=nom').then((value)=>{
        const namee = value['nom'];
        nameInput.value= namee;   
    })
document.getElementById('changer-nom').addEventListener('click',async()=>{
    const name= nameInput.value;
    console.log(name);
    await simple_fetch('chat.php?action=changer-nom',{post:{nom}});
})

rafraichir.addEventListener('click', rafraichissement)

async function rafraichissement(){
    console.log('rafraîchir');
    try{
        let reponse=await simple_fetch('http://localhost/~12203019/chat.php?action=messages');
        messages.innerHTML='';
        
        for(let i=0;i<reponse.length;i++){
            console.log(reponse[i])
        }
    }
    catch(e){
        console.log('Erreur de récupération des messages '+ e.error);
        return;
    }

    
   
}