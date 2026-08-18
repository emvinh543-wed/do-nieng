// World components for Duck Game - adds buildings, sea, rails, mountains, pond, lighthouse
// Exposes initWorld(scene, camera, renderer) and setWeather(type)
(function(window){
    window.WorldComponents = {};

    function addBuildings(scene) {
        const mat = new THREE.MeshStandardMaterial({ color: 0x8b5a2b, metalness:0.05, roughness:0.6 });
        for (let i=0;i<6;i++){
            const h = 20 + Math.random()*60;
            const geo = new THREE.BoxGeometry(20, h, 14);
            const mesh = new THREE.Mesh(geo, mat.clone());
            mesh.position.set(-200 + i*70, h/2 - 2, -120 - (i%2)*40);
            mesh.material.color.offsetHSL(0,0, (Math.random()-0.5)*0.06);
            scene.add(mesh);
        }
    }

    function addSea(scene) {
        const geo = new THREE.PlaneGeometry(1200,600,1,1);
        const mat = new THREE.MeshStandardMaterial({ color:0x2b6ea3, roughness:0.8, metalness:0.1, transparent:true, opacity:0.95 });
        const sea = new THREE.Mesh(geo, mat);
        sea.rotation.x = -Math.PI/2;
        sea.position.set(0, -6, 280);
        scene.add(sea);
    }

    function addRails(scene) {
        const railMat = new THREE.MeshStandardMaterial({ color:0x333333, metalness:0.6, roughness:0.4 });
        const sleeperMat = new THREE.MeshStandardMaterial({ color:0x6b4a2a });
        for (let i=0;i<60;i++){
            const rail = new THREE.Mesh(new THREE.BoxGeometry(200,0.6,0.6), railMat);
            rail.position.set(-400 + i*14, -4.6, -20);
            scene.add(rail);
            const sleeper = new THREE.Mesh(new THREE.BoxGeometry(2.5,0.4,6), sleeperMat);
            sleeper.position.set(-400 + i*14, -5.2, -20);
            scene.add(sleeper);
        }
    }

    function addMountains(scene) {
        for (let i=0;i<5;i++){
            const h = 60 + Math.random()*140;
            const geo = new THREE.ConeGeometry( h*0.6, h, 8 );
            const mat = new THREE.MeshStandardMaterial({ color:0x4b3b2a, roughness:1 });
            const mesh = new THREE.Mesh(geo, mat);
            mesh.position.set(-300 + i*150, h/2 - 8, 500 + (i%2)*80);
            mesh.rotation.y = Math.random()*Math.PI;
            scene.add(mesh);
        }
    }

    function addPond(scene) {
        const geo = new THREE.CircleGeometry(50, 32);
        const mat = new THREE.MeshStandardMaterial({ color:0x1e90ff, roughness:0.9, metalness:0.05, transparent:true, opacity:0.9 });
        const pond = new THREE.Mesh(geo, mat);
        pond.rotation.x = -Math.PI/2;
        pond.position.set(180, -6, -80);
        scene.add(pond);
    }

    function addLighthouse(scene) {
        const base = new THREE.Mesh(new THREE.CylinderGeometry(6,8,24,16), new THREE.MeshStandardMaterial({color:0xffffff}));
        base.position.set(320,6,300);
        scene.add(base);
        const top = new THREE.Mesh(new THREE.CylinderGeometry(3.5,3.5,8,12), new THREE.MeshStandardMaterial({color:0xffcc00}));
        top.position.set(320,18,300);
        scene.add(top);
    }

    // basic particle rain/snow generator
    let particleGroup = null;
    function createParticles(scene, count=400, color=0xffffff) {
        if (particleGroup){ scene.remove(particleGroup); particleGroup = null; }
        const geom = new THREE.BufferGeometry();
        const pos = new Float32Array(count*3);
        for (let i=0;i<count;i++){ pos[i*3+0] = (Math.random()-0.5)*800; pos[i*3+1] = Math.random()*300 + 20; pos[i*3+2] = (Math.random()-0.5)*800; }
        geom.setAttribute('position', new THREE.BufferAttribute(pos,3));
        const mat = new THREE.PointsMaterial({ color: color, size: 1.8, transparent:true, opacity:0.85 });
        particleGroup = new THREE.Points(geom, mat);
        scene.add(particleGroup);
    }

    function updateParticles(delta){
        if (!particleGroup) return;
        const pos = particleGroup.geometry.attributes.position.array;
        for (let i=0;i<pos.length/3;i++){
            pos[i*3+1] -= 200 * delta; // speed
            if (pos[i*3+1] < -20) pos[i*3+1] = Math.random()*300 + 120;
        }
        particleGroup.geometry.attributes.position.needsUpdate = true;
    }

    let currentWeather = 'clear';
    function setWeather(scene, type){
        currentWeather = type;
        if (type === 'rain') createParticles(scene, 800, 0x9fd6ff);
        else if (type === 'storm') createParticles(scene, 1200, 0xa8d0ff);
        else if (type === 'snow') createParticles(scene, 600, 0xffffff);
        else { if (particleGroup){ scene.remove(particleGroup); particleGroup = null; } }
    }

    // eat effect triggered by python server -> spawn temporary particles
    function spawnEatEffect(scene, position){
        const group = new THREE.Group();
        for (let i=0;i<24;i++){
            const geo = new THREE.SphereGeometry(0.8,6,6);
            const mat = new THREE.MeshStandardMaterial({ color: 0xffe082, emissive:0xffd54f });
            const m = new THREE.Mesh(geo, mat);
            m.position.set(position.x + (Math.random()-0.5)*4, position.y + Math.random()*3, position.z + (Math.random()-0.5)*4);
            group.add(m);
        }
        scene.add(group);
        // fade and remove
        let t=0;
        const intv = setInterval(()=>{
            t+=0.06;
            group.children.forEach((c,i)=>{ c.position.y += 0.12 + Math.random()*0.06; c.material.opacity = Math.max(0,1-t); c.material.transparent = true; });
            if (t>1.2){ clearInterval(intv); scene.remove(group); }
        }, 60);
    }

    // public init
    window.WorldComponents.initWorld = function(scene){
        addSea(scene);
        addBuildings(scene);
        addRails(scene);
        addMountains(scene);
        addPond(scene);
        addLighthouse(scene);
        // ambient extras
        const hemi = new THREE.HemisphereLight(0xffffff, 0x444444, 0.5);
        scene.add(hemi);
    };

    window.WorldComponents.setWeather = setWeather;
    window.WorldComponents.updateParticles = updateParticles;
    window.WorldComponents.spawnEatEffect = spawnEatEffect;

})(window);
