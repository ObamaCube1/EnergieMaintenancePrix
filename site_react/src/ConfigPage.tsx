import { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import Navigation from "./Navigation.tsx";
import { useCentraleContext } from "./CentraleContext";
import './ConfigPage.css'

// Types

type Turbine = {
    nom: string;
    pMini?: number;
    pMaxi?: number;
    tAMax?: number;
    modeChoisi?: "ML" | "OA" | "OA-";
    palierOAe?: number;
    palierOAh?: number;
    dateEte?: string;
    dateHiver?: string;
    arretPossible?: boolean;
    reductionPossible?: boolean;
};

type Centrale = {
    nom: string;
    vnf?: boolean;
    seuil?: number;
    listeTurbines?: Turbine[];
};

export default function ConfigPage() {
    const { nom } = useParams<{ nom: string }>();
    const navigate = useNavigate();
    const [centrale, setCentrale] = useState<Centrale | null>(null);
    const [ancienneNom, setAncienneNom] = useState<string>("");
    const { reloadSidebar } = useCentraleContext();

    useEffect(() => {
        fetch(`http://localhost/manipRTE.php?action=GetCentrale&nom=${nom}`)
            .then(res => res.json())
            .then((data: Centrale) => {
                setCentrale(data);
                setAncienneNom(data.nom);
            })
            .catch(err => console.error("Erreur de chargement :", err));
    }, [nom]);

    if (!centrale) return <div>Chargement…</div>;

    const updateTurbine = <K extends keyof Turbine>(index: number, field: K, value: Turbine[K]) => {
        if (!centrale?.listeTurbines) return;
        const updated = [...centrale.listeTurbines];
        updated[index] = {
            ...updated[index],
            [field]: value,
        };
        setCentrale({ ...centrale, listeTurbines: updated });
    };

    const handleSauvegarde = async () => {
        if (!centrale) return;
        try {
            const res = await fetch("http://localhost/manipRTE.php?action=UpdateCentrale", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ ancienneNom, centrale })
            });

            if (!res.ok) {
                const text = await res.text();
                console.error("Erreur lors de la sauvegarde :", text);
                alert("Échec de la sauvegarde.");
            } else {
                const nouvelleNom = centrale.nom;
                alert("Centrale sauvegardée avec succès !");
                reloadSidebar();
                if (ancienneNom !== nouvelleNom) {
                    navigate(`/${encodeURIComponent(nouvelleNom)}/config`, { replace: true });
                }
            }
        } catch (error) {
            console.error("Erreur réseau :", error);
            alert("Erreur réseau lors de la sauvegarde.");
        }
    };

    const addTurbine = () => {
        const nouvelle: Turbine = { nom: "" };
        setCentrale({
            ...centrale,
            listeTurbines: [...(centrale.listeTurbines ?? []), nouvelle],
        });
    };

    const removeTurbine = (index: number) => {
        const updated = [...(centrale.listeTurbines ?? [])];
        updated.splice(index, 1);
        setCentrale({ ...centrale, listeTurbines: updated });
    };

    const handleDeleteCentrale = async () => {
        const confirmation = window.confirm(`Supprimer la centrale "${centrale?.nom}" ? Cette action est irréversible.`);
        if (!confirmation || !centrale) return;

        try {
            const res = await fetch(`http://localhost/manipRTE.php?action=DeleteCentrale&nom=${encodeURIComponent(centrale.nom)}`, {
                method: "GET"
            });

            if (!res.ok) {
                const text = await res.text();
                console.error("Erreur de suppression :", text);
                alert("Échec de la suppression de la centrale.");
            } else {
                alert("Centrale supprimée avec succès.");
                reloadSidebar();
                navigate("/");
            }
        } catch (error) {
            console.error("Erreur réseau :", error);
            alert("Erreur réseau lors de la suppression.");
        }
    };


    return (
        <div>
            <Navigation/>
            <h2>Configuration de {centrale.nom}</h2>
            <button className="good" onClick={addTurbine}>Ajouter une turbine</button>
            <button className="save" onClick={handleSauvegarde}>Sauvegarder</button>
            <button className="bad" onClick={handleDeleteCentrale}>Supprimer la centrale</button>
            <br/>
            <fieldset className="fieldset-container">
            <label>
                Nom : <input
                type="text"
                value={centrale.nom}
                onChange={(e) => setCentrale({...centrale, nom: e.target.value})}
            />
            </label>

            <label>
                <input
                    type="checkbox"
                    checked={centrale.vnf ?? false}
                    onChange={(e) => setCentrale({...centrale, vnf: e.target.checked})}
                /> VNF
            </label><br/>

            <label>
                Seuil de calcul (en €) : <input
                type="number"
                value={centrale.seuil ?? ''}
                onChange={(e) => setCentrale({...centrale, seuil: parseFloat(e.target.value)})}
                step="0.01"
            />
            </label>
            </fieldset>

            <h3>Turbines</h3>
            <div className="listeTurbines">
                {(centrale.listeTurbines ?? []).map((t, index) => (
                    <fieldset className="fieldset-container">
                        <div key={index}>
                            <label>Nom : <input value={t.nom}
                                                onChange={(e) => updateTurbine(index, 'nom', e.target.value)}/></label><br/>
                            <label>Puissance minimale (W) : <input type="number" value={t.pMini ?? ''}
                                                                   onChange={(e) => updateTurbine(index, 'pMini', parseInt(e.target.value))}/></label><br/>
                            <label>Puissance maximale (W) : <input type="number" value={t.pMaxi ?? ''}
                                                                   onChange={(e) => updateTurbine(index, 'pMaxi', parseInt(e.target.value))}/></label><br/>
                            <label>Temps d'arrêt maximal (minutes) : <input type="number" value={t.tAMax ?? ''}
                                                                            onChange={(e) => updateTurbine(index, 'tAMax', parseInt(e.target.value))}/></label><br/>
                            <label>Palier OA été : <input type="number" step="0.01" value={t.palierOAe ?? ''}
                                                          onChange={(e) => updateTurbine(index, 'palierOAe', parseFloat(e.target.value))}/></label><br/>
                            <label>Palier OA hiver : <input type="number" step="0.01" value={t.palierOAh ?? ''}
                                                            onChange={(e) => updateTurbine(index, 'palierOAh', parseFloat(e.target.value))}/></label><br/>
                            <label>Mode choisi :
                                <select
                                    value={t.modeChoisi ?? ''}
                                    onChange={(e) => updateTurbine(index, 'modeChoisi', e.target.value as "ML" | "OA" | "OA-")}
                                >
                                    <option value="">-- Choisir un mode --</option>
                                    <option value="ML">ML</option>
                                    <option value="OA">OA</option>
                                    <option value="OA-">OA-</option>
                                </select>
                            </label><br/>
                            <label>Date été : <input type="date" value={t.dateEte ?? ''}
                                                     onChange={(e) => updateTurbine(index, 'dateEte', e.target.value)}/></label><br/>
                            <label>Date hiver : <input type="date" value={t.dateHiver ?? ''}
                                                       onChange={(e) => updateTurbine(index, 'dateHiver', e.target.value)}/></label><br/>
                            <label><input type="checkbox" checked={t.arretPossible ?? false}
                                          onChange={(e) => updateTurbine(index, 'arretPossible', e.target.checked)}/> Arrêt
                                possible</label><br/>
                            <label><input type="checkbox" checked={t.reductionPossible ?? false}
                                          onChange={(e) => updateTurbine(index, 'reductionPossible', e.target.checked)}/> Réduction
                                possible</label><br/>
                            <button className="bad" onClick={() => removeTurbine(index)}>Supprimer</button>
                        </div>
                    </fieldset>
                ))}
            </div>
        </div>
    );
}
