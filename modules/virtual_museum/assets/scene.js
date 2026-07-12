import * as THREE from 'three';

export function addFloorRect(scene, rect, y, material, faceDown = false) {
    const width = rect.x1 - rect.x0;
    const length = rect.z1 - rect.z0;
    const mesh = new THREE.Mesh(new THREE.PlaneGeometry(width, length), material);
    mesh.rotation.x = faceDown ? Math.PI / 2 : -Math.PI / 2;
    mesh.position.set((rect.x0 + rect.x1) / 2, y, (rect.z0 + rect.z1) / 2);
    scene.add(mesh);
    return mesh;
}

export function addWallSegment(scene, wall, height, thickness, material) {
    const dx = wall.x2 - wall.x1;
    const dz = wall.z2 - wall.z1;
    const mesh = new THREE.Mesh(new THREE.BoxGeometry(Math.hypot(dx, dz), height, thickness), material);
    mesh.position.set((wall.x1 + wall.x2) / 2, height / 2, (wall.z1 + wall.z2) / 2);
    mesh.rotation.y = -Math.atan2(dz, dx);
    scene.add(mesh);
    return mesh;
}

export function disposeScene(scene, renderer, extraTextures = []) {
    extraTextures.filter(Boolean).forEach(texture => texture.dispose?.());
    scene.traverse(object => {
        object.geometry?.dispose?.();
        const materials = Array.isArray(object.material) ? object.material : [object.material];
        materials.filter(Boolean).forEach(material => {
            material.map?.dispose?.();
            material.dispose?.();
        });
    });
    renderer?.dispose?.();
}
